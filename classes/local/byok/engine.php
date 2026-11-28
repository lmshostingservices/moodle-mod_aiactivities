<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_aiactivities\local\byok;

/**
 * The LMS Labs activity generation routes, ported to run on the site's own AI (Bring your own AI).
 *
 * A line-for-line port of POST /api/generate-aiactivities and POST /api/aiactivities-regenerate-audio:
 * the same request checks, prompts, answer checks and narration, so a teacher gets the same activities
 * whichever AI writes them. Credits are not counted: the site pays its own AI provider.
 *
 * @package    mod_aiactivities
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class engine {
    /** JavaScript's whitespace, for String.prototype.trim(). */
    public const WS = '\x{0009}-\x{000D}\x{0020}\x{00A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}\x{FEFF}';

    /** The fixed activity order, cycled when more than eight are asked for. */
    public const ACTIVITY_TYPES = ['cardselect', 'columnsort', 'flashcards', 'matching', 'truefalseswipe', 'categorysort',
        'fillinblank', 'ordering'];

    /** Language codes the service names in its prompts. */
    public const LANGUAGES = [
        'en-AU' => 'Australian English',
        'en-GB' => 'British English',
        'en-US' => 'American English',
        'en-IN' => 'Indian English',
        'en-NZ' => 'New Zealand English',
        'en-CA' => 'Canadian English',
        'en-ZA' => 'South African English',
        'en-IE' => 'Irish English',
        'es-ES' => 'Spanish (Spain)',
        'fr-FR' => 'French',
        'de-DE' => 'German',
        'it-it' => 'Italian',
        'pt-BR' => 'Brazilian Portuguese',
        'nl-NL' => 'Dutch',
        'pl-PL' => 'Polish',
        'tr-TR' => 'Turkish',
        'sv-SE' => 'Swedish',
        'da-DK' => 'Danish',
        'nb-NO' => 'Norwegian',
        'fi-FI' => 'Finnish',
        'zh-CN' => 'Simplified Chinese (Mandarin)',
        'ja-JP' => 'Japanese',
        'ko-KR' => 'Korean',
        'hi-IN' => 'Hindi',
        'th-TH' => 'Thai',
        'vi-VN' => 'Vietnamese',
        'id-ID' => 'Indonesian',
        'ms-MY' => 'Malay',
        'tl-PH' => 'Filipino (Tagalog)',
        'ar-SA' => 'Arabic',
    ];

    /** @var callable fn(string $system, string $user, array $options): string. Options: maxtokens, json, temperature. */
    public static $aicall = null;
    /** @var callable fn(string $text, array $voice): string audio bytes. Voice: languageCode, name, id. */
    public static $tts = null;

    /**
     * POST /api/generate-aiactivities.
     *
     * @param mixed $body The request body, decoded as the service decodes it.
     * @return array The route's answer.
     * @throws http_error When the route answers with an error status.
     */
    public static function generate($body): array {
        $v = self::validate(is_array($body) ? $body : []);
        if (is_string($v)) {
            throw new http_error(400, 'Validation error: ' . $v);
        }
        $content = $v['content'];
        $language = $v['language'];
        $activitycount = $v['activityCount'];
        $activityminutes = $v['activityMinutes'];
        $sessionminutes = $v['sessionMinutes'];
        $scenariomode = $v['scenarioMode'];
        $scenariocountry = $v['scenarioCountry'];
        $scenarioindustry = $v['scenarioIndustry'];
        $scenarioworkerlevel = $v['scenarioWorkerLevel'];
        $route = $v['route'];
        $spelling = $v['spelling'];
        $country = $v['country'];
        $state = $v['state'];
        $industry = $v['industry'];
        $joblevel = $v['jobLevel'];
        $teachernotes = $v['teacherNotes'];
        $voiceoverenabled = $v['voiceoverEnabled'];
        $voiceid = $v['voiceId'];

        $sequence = [];
        for ($i = 0; $i < $activitycount; $i++) {
            $sequence[] = self::ACTIVITY_TYPES[$i % count(self::ACTIVITY_TYPES)];
        }
        $languagename = self::LANGUAGES[$language] ?? $language;
        if (self::jtrim($spelling) !== '') {
            $spellingrule = 'Use ' . self::jtrim($spelling) . ' spelling consistently in every activity.';
        } else if ($languagename === 'Australian English') {
            $spellingrule = 'Use Australian spelling throughout (organisation, behaviour, colour, centre, recognise, ' .
                'analyse, practise as a verb).';
        } else if ($languagename === 'British English' || $languagename === 'New Zealand English') {
            $spellingrule = 'Use the requested British/New Zealand spelling consistently (organisation, behaviour, colour, ' .
                'centre, recognise, analyse, practise as a verb).';
        } else if ($languagename === 'American English') {
            $spellingrule = 'Use American spelling consistently (organization, behavior, color, center, recognize, analyze, ' .
                'practice as a verb).';
        } else {
            $spellingrule = 'Use the spelling conventions of ' . $languagename . '.';
        }
        $durationguidance = self::duration_guidance($activitycount, $sessionminutes, $activityminutes);
        $systemprompt = ('You are an expert instructional designer creating interactive revision activities from learning ' .
            'content. You always respond with valid JSON. Your goal is to help learners truly understand the material — ' .
            'not just test them, but teach them through well-crafted questions, clear explanations, and meaningful feedback.

NON-NEGOTIABLE RULES:
1. Always return valid JSON only. No commentary outside JSON.
2. JSON keys MUST remain in English. Only VALUES are in the target language.
3. Do not repeat the same learning objective across activities.
4. Avoid vague or generic language. Prefer direct verbs, concrete nouns, and specific actions.
5. Avoid moral signalling or obvious "safe sounding" correct answers.
6. Ensure distractors are plausible and structurally similar to correct answers. All distractors MUST come from the same ' .
            'domain as the content — never use obviously unrelated options (e.g., "Healthy Lunches" as a distractor for ' .
            'workplace hazards). Every distractor must be something a learner who has not studied carefully might genuinely ' .
            'choose.
7. In any language, avoid vague corporate/marketing language. If the target language has common buzzword equivalents, do not ' .
            'use them.
8. Every fill-in-the-blank sentence must read as a complete, grammatically correct sentence when blanks are filled.
9. Flashcard backs must give precise, factual answers — not vague summaries. Minimum 40 characters. Include enough detail ' .
            'that a learner who did not know the answer learns something useful by reading the back.
10. True/false explanations must TEACH the learner — do not just restate the statement. Explain the underlying fact, cite ' .
            'a specific detail from the source content, and help the learner understand WHY the answer is what it is.
11. SOURCE AND CONTEXT FIDELITY: Treat the supplied learning content as the only authority for factual claims. Never invent ' .
            'legal rules, numeric thresholds (including lifting weights), statistics, policies, locations, equipment or ' .
            'workplace practices. Use supplied country, state, industry and job level only; do not add a city or setting ' .
            'that was not provided. Follow the explicit spelling setting when one is supplied. If a teacher instruction ' .
            'conflicts with source accuracy or safety, keep the source-accurate and safe content.
12. ACTIVITY SIZE: ' . self::js_str($durationguidance) . ' Keep each activity focused on one learning point, using ' .
        'reflection, application and feedback for appropriate depth. Respect the item counts specified for its type; do not ' .
            'pad ' .
        'it with repeated questions or unrelated content.

EXPLANATION QUALITY RULES (applies to truefalseswipe explanations and all feedback text):
- NEVER start explanations with "This is true because..." or "This is false because..." — these are formulaic and do not ' .
        'teach.
- Instead, lead with the actual fact: "The hierarchy of controls places elimination at the top as the most effective ' .
        'measure, followed by substitution, engineering controls, administrative controls, and PPE at the bottom."
- For FALSE statements: explain what the correct fact actually is, so the learner walks away knowing the right answer. ' .
        'Example: "PPE is actually the least effective control in the hierarchy. Elimination is the most effective because ' .
            'it ' .
        'completely removes the hazard from the workplace."
- For TRUE statements: add context or a detail that deepens understanding. Example: "Workers are legally required to report ' .
        'hazards immediately. Under WHS legislation, failing to report a known hazard can result in penalties for both the ' .
        'worker and the employer."
- Explanations should be 1-3 sentences and written at a Year 10 reading level in plain, simple language.

ACTIVITY TYPE SPECIFICATIONS:

1. "cardselect" - Multiple choice with 4 cards
   - A question about the content that tests understanding, not just recall
   - Exactly 4 answer options, each with a short label (2-4 words or equivalent length, max 18 characters in the target ' .
        'language) and description (1 sentence)
   - Exactly 1 correct answer (correctIndex: 0-3)
   - The 3 incorrect options (distractors) must be plausible and related to the content but clearly distinguishable from the ' .
        'correct answer
   - CRITICAL: Every distractor must come from the same subject domain. If the question is about safety hazards, all 4 ' .
        'options must be things that could plausibly be hazards. If the question is about procedures, all 4 options must be ' .
        'plausible procedures. Never mix domains.
   - Each distractor should represent a common misconception or a related-but-wrong concept
   - HOMOGENEITY: All 4 descriptions must be similar length (within 20%) and use the same grammatical structure
   - Do NOT make the correct card obviously longer, safer-sounding, or more positive than distractors
   - An icon keyword for each card from: ["shield", "book", "lightbulb", "gear", "heart", "star", "check", "warning", ' .
        '"clock", "target", "users", "tool", "flag", "eye", "lock"]

2. "columnsort" - Sort items into 2 columns (binary classification)
   - A question that frames the sorting task (e.g., "Which of these rust prevention methods belong to each category?")
   - A clear theme (e.g., "Safe vs Unsafe", "Correct vs Incorrect", "Do vs Don\'t")
   - Column A name and Column B name (short, 1-3 words each)
   - 6-8 items, each assigned to either column "A" or "B"
   - Items MUST be roughly balanced between columns (e.g., 3-4 in each for 6 items, 4 in each for 8 items)
   - Each item must clearly and unambiguously belong to its assigned column
   - Items appear one at a time

3. "flashcards" - Flip cards for self-study revision
   - A title describing the topic being revised (e.g., "Key Terms in Workplace Safety")
   - A question that frames what the learner is revising (e.g., "Test your knowledge of these key concepts")
   - 5-8 cards, each with a "front" (a question or prompt that makes the learner think - max 40 characters) and "back" (a ' .
        'clear, teaching answer - minimum 50 characters, max 120 characters, must be 1-2 complete sentences)
   - Front text should be phrased as a question where possible (e.g., "What is the hierarchy of controls?" rather than just ' .
        '"Hierarchy of Controls")
   - Back text must TEACH — include enough detail that someone who did not know the answer learns the key fact. Bad: ' .
        '"Anything that could cause harm." Good: "A hazard is anything with the potential to cause harm to people, property, ' .
            'or ' .
        'the environment — such as chemicals, noise, or unguarded machinery."
   - Cards should cover different concepts from the source material
   - Order cards from foundational concepts to more advanced ones
   - BALANCE: Front text lengths should be similar across cards (within 30%). Back text lengths should be similar across ' .
        'cards (within 30%).

4. "matching" - Match text pairs
   - A question that frames the matching task (e.g., "Can you match each safety term to its correct definition?")
   - 4-6 pairs of related items (left text matches right text)
   - Left items are terms/concepts, right items are definitions/descriptions
   - Right-side definitions should be clear enough that a learner can understand the term just by reading the definition
   - Each right-side definition must be distinct enough that it can only match ONE left-side term
   - Keep text concise (under 60 characters each side)

5. "truefalseswipe" - Judge statements as true or false
   - A title describing the topic (e.g., "Fact Check: Fire Safety Procedures")
   - A question that frames the task (e.g., "Decide whether each statement is true or false")
   - 6-8 statements, each with "text" (the statement, 1 clear sentence), "correct" (boolean - true if the statement is true, ' .
        'false if false), and "explanation" (1-3 sentences that TEACH the learner the underlying fact — see EXPLANATION ' .
        'QUALITY RULES above)
   - Mix of true and false statements: aim for roughly 50/50 split (e.g., 3-4 true, 3-4 false for 6-8 statements)
   - Statements must be clear and unambiguous - avoid "sometimes" or "usually" qualifiers that make truth value debatable
   - False statements must be clearly false based on the content (not trick questions or technicalities)
   - True statements should test actual knowledge, not be obviously true platitudes
   - AVOID statements that could be argued either way - every statement must have a definitive answer

6. "categorysort" - Sort items into categories
   - A question that frames the sorting task (e.g., "What are the different categories of rust prevention methods?")
   - 3 or 4 distinct category names (short, 1-3 words each)
   - 6-12 items, each belonging to exactly one category
   - The "category" field on each item MUST be the exact string name of one of the categories (NOT a number)
   - Distribute items roughly evenly across all categories (at least 2 items per category)
   - Items must be unambiguously sortable into their correct category with no overlap

7. "fillinblank" - Fill in missing words from a word bank
   - A title describing the topic (e.g., "Complete the Safety Procedure")
   - A question that frames the task (e.g., "Fill in the missing words to complete this important procedure")
   - "text": A paragraph of 3-4 sentences from the content with key words replaced by numbered blanks: ___1___, ___2___, ' .
        'etc. The text must make grammatical sense when blanks are filled. The passage should teach a complete concept — ' .
            'not ' .
        'just a single sentence.
   - "blanks": Array of objects, each with "answer" (the correct word/short phrase, max 25 characters) and "position" ' .
        '(0-indexed, matching the blank number minus 1)
   - "distractors": Array of 3-4 plausible but incorrect words/phrases that could fit in blanks but are wrong. Distractors ' .
        'must be the same type of word (nouns for nouns, verbs for verbs) and similar length to correct answers. Every ' .
        'distractor must come from the same subject domain.
   - Use 4-5 blanks per activity
   - Blank answers should be KEY terms that test understanding, not filler words like "the" or "and"
   - Each blank answer must be a single word or very short phrase (1-3 words max)
   - The sentence with blanks filled must be factually accurate based on the source content

8. "ordering" - Arrange steps in correct sequence
   - A question that frames the ordering task (e.g., "What is the correct order for completing a risk assessment?")
   - A clear title describing what to order (e.g., "Steps to complete a risk assessment")
   - 4-7 items that have a definite, unambiguous correct order
   - Each item MUST start with an action verb and read as a clear, distinct step (e.g., "Identify the hazards in the ' .
        'workplace", "Assess the level of risk for each hazard")
   - Items must be specific procedural steps, NOT vague concepts or principles
   - The sequence must have a logical flow where each step clearly follows the previous one
   - Items presented shuffled, student must arrange correctly

SCENARIO MODE RULES:
When scenario mode is enabled, EVERY SINGLE activity — all 8 types without exception — must include a scenario in the ' .
        '"question" field. This includes flashcards, matching, truefalseswipe, fillinblank, and ordering — not just ' .
            'cardselect ' .
        'and columnsort.

The scenario MUST appear at the start of the "question" field, followed by a blank line, then the actual question or ' .
        'instruction. For example:
- flashcards question: "Mei is a new apprentice electrician reviewing her safety handbook before starting on a Melbourne ' .
        'construction site.\\n\\nTest your knowledge of these key electrical safety concepts."
- truefalseswipe question: "Carlos is a trainee chef at a busy hotel kitchen in Sydney. His supervisor quizzes him on food ' .
        'safety rules before his first solo shift.\\n\\nDecide whether each statement is true or false."
- fillinblank question: "Priya is writing up her workplace induction notes after her first day at an aged care facility in ' .
        'Brisbane.\\n\\nComplete the passage with the correct key terms."

Every scenario must use a different character name and feel like a real situation someone would actually encounter.

OUTPUT FORMAT: Return a JSON object with this exact structure. All JSON keys (type, title, question, cards, label, ' .
        'description, icon, correctIndex, theme, columnA, columnB, items, text, column, categories, category, pairs, left, ' .
        'right, instruction, front, back, correct, explanation, blanks, answer, position, distractors, statements) MUST ' .
            'remain ' .
        'in English. Only the VALUES (the actual content text) should be in the target language.
{
  "activities": [
    {
      "type": "cardselect",
      "title": "Short activity title",
      "question": "The question to answer",
      "cards": [
        {"label": "Short Label", "description": "One sentence description", "icon": "shield"},
        {"label": "Short Label", "description": "One sentence description", "icon": "book"},
        {"label": "Short Label", "description": "One sentence description", "icon": "lightbulb"},
        {"label": "Short Label", "description": "One sentence description", "icon": "gear"}
      ],
      "correctIndex": 0,
      "explanations": [
        "Correct. One-sentence reason this card is the right answer.",
        "Incorrect. One-sentence reason this card is wrong and what would make it right.",
        "Incorrect. One-sentence reason this card is wrong and what would make it right.",
        "Incorrect. One-sentence reason this card is wrong and what would make it right."
      ]
    },
    {
      "type": "columnsort",
      "title": "Activity title",
      "question": "A question that frames the sorting task for the learner",
      "theme": "Safe vs Unsafe",
      "columnA": "Safe",
      "columnB": "Unsafe",
      "items": [
        {"text": "Item text here", "column": "A"},
        {"text": "Item text here", "column": "B"}
      ]
    },
    {
      "type": "flashcards",
      "title": "Key Terms in Topic",
      "question": "Test your knowledge of these key concepts",
      "cards": [
        {"front": "What is a hazard?", "back": "A hazard is anything with the potential to cause harm — such as chemicals, ' .
        'noise, or unguarded machinery."},
        {"front": "What does PPE stand for?", "back": "Personal Protective Equipment — items worn to reduce exposure to ' .
        'hazards, such as gloves, helmets, and safety glasses."}
      ]
    },
    {
      "type": "matching",
      "title": "Activity title",
      "question": "A question that frames the matching task for the learner",
      "pairs": [
        {"left": "Term", "right": "Clear definition that teaches the concept"},
        {"left": "Term", "right": "Clear definition that teaches the concept"}
      ]
    },
    {
      "type": "truefalseswipe",
      "title": "Fact Check: Topic",
      "question": "Decide whether each statement is true or false",
      "statements": [
        {"text": "A clear factual statement", "correct": true, "explanation": "The hierarchy of controls places elimination ' .
        'at the top because removing the hazard entirely is always more effective than trying to manage it with equipment or ' .
        'rules."},
        {"text": "A plausible but false statement", "correct": false, "explanation": "PPE is actually ranked last in the ' .
        'hierarchy of controls. Elimination, substitution, and engineering controls are all more effective because they ' .
            'reduce ' .
        'the hazard itself rather than relying on the worker to protect themselves."}
      ]
    },
    {
      "type": "categorysort",
      "title": "Activity title",
      "question": "A question that frames the categorisation task for the learner",
      "categories": ["Category 1", "Category 2", "Category 3"],
      "items": [
        {"text": "Item text", "category": "Category 1"},
        {"text": "Item text", "category": "Category 2"},
        {"text": "Item text", "category": "Category 3"}
      ]
    },
    {
      "type": "fillinblank",
      "title": "Complete the Key Procedure",
      "question": "Fill in the missing words to complete this important information",
      "text": "Before entering a work area, workers must wear ___1___ to reduce exposure to hazards. The ___2___ of controls ' .
        'ranks control measures from most to least effective. The most effective control is ___3___, which means completely ' .
        'removing the hazard. The least effective control is ___4___, which relies on workers wearing protective gear.",
      "blanks": [
        {"answer": "PPE", "position": 0},
        {"answer": "hierarchy", "position": 1},
        {"answer": "elimination", "position": 2},
        {"answer": "PPE", "position": 3}
      ],
      "distractors": ["substitution", "monitoring", "consultation"]
    },
    {
      "type": "ordering",
      "title": "Steps to Report a Workplace Incident",
      "question": "A question that frames the ordering task for the learner",
      "instruction": "Arrange these steps in the correct order",
      "items": ["Ensure the area is safe and attend to any injuries", "Notify your immediate supervisor of the incident", ' .
        '"Complete the workplace incident report form", "Submit the report and preserve any evidence"]
    }
  ]
}

QUALITY RULES:
- Each activity must cover DIFFERENT content/concepts from the source material (no repetition across activities)
- Vary question stems and approaches - don\'t start every title/question the same way
- Card labels must be concise (2-4 words or equivalent length, max 18 characters in the target language, mobile-friendly)
- Card descriptions must be one clear sentence (max 2 lines on mobile)
- Distractors must be plausible and content-related but clearly distinguishable from the correct answer
- For cardselect: all 4 descriptions must be similar length (within 20%) and same grammatical structure. ALL 4 options must ' .
        'come from the same subject domain — never mix workplace concepts with obviously unrelated topics.
- Items must be unambiguous - only one correct answer/placement possible
- Use specific concepts, terms, and examples directly from the provided content - not generic placeholders
- For ordering activities, each item must be a clear action step starting with a verb (e.g., "Identify...", "Complete...", ' .
        '"Review...") with an obvious sequential order
- For matching, ensure each definition uniquely identifies its term with no cross-matching possible. Definitions should be ' .
        'clear enough to teach the concept.
- For flashcards: fronts should be phrased as questions where possible. Backs must be minimum 50 characters and teach the ' .
        'concept — not just name it. Fronts should be similar length, backs should be similar length.
- For truefalseswipe: mix true and false roughly equally. Every explanation must teach — never just restate the statement. ' .
        'Lead with the actual fact, add context, help the learner understand why.
- For fillinblank: blanked words must be KEY terms that test knowledge. Use 4-5 blanks with a 3-4 sentence passage. The word ' .
        'bank (correct answers + distractors) must all be the same type of word and from the same domain. The completed ' .
            'passage ' .
        'should teach a complete concept. CRITICAL: The passage MUST be fully complete — NEVER end mid-sentence or with ' .
            '"..." ' .
        '(ellipsis). Every sentence must be grammatically complete.
- Keep all text professional, educational, and appropriate for the learner\'s context (university, workplace, or vocational)
- Write at a Year 10 reading level. Use short sentences and plain English. Avoid jargon unless it is a key term being taught.

SELF-CHECK BEFORE RETURN:
Before returning JSON, verify:
1. There are exactly the requested number of activities in the correct sequence of types.
2. No duplicate questions or learning objectives across activities.
3. correctIndex values are valid (0-3) for cardselect activities.
4. All cardselect options are similar length (within 20%) and grammatically parallel, and ALL from the same domain.
5. JSON keys are in English, content values in the target language.
6. No commentary or text outside the JSON object.
7. If scenario mode is enabled: EVERY activity (all 8 types) has a scenario in the "question" field — check flashcards, ' .
        'matching, truefalseswipe, fillinblank, and ordering specifically.
8. Flashcard backs are minimum 50 characters and teach the concept with enough detail to be useful.
9. True/false explanations TEACH — they never start with "This is true/false because" and they add context beyond ' .
        'restating the statement.
10. Fill-in-the-blank has 4-5 blanks across 3-4 sentences, and distractors are from the same domain as correct answers.
11. Fill-in-the-blank passage does NOT end with "..." or any truncated sentence — every sentence is grammatically complete.
12. All content uses the SPELLING rules specified above — check every true/false statement, explanation, and label.
Return only valid JSON.');

        $lines = [];
        foreach (
            [
            $country !== '' ? 'Country: ' . $country : '',
            $state !== '' ? 'State or region: ' . $state : '',
            $industry !== '' ? 'Industry: ' . $industry : '',
            $joblevel !== '' ? 'Job level: ' . $joblevel : '',
            self::jtrim($teachernotes) !== ''
                ? "Teacher instructions (follow when consistent with the supplied content and safety):\n"
                    . self::jtrim($teachernotes)
                : '',
            ] as $line
        ) {
            if ($line !== '') {
                $lines[] = $line;
            }
        }
        $contextlines = implode("\n", $lines);
        $userprompt = ('Generate exactly ' . self::js_str($activitycount) . ' interactive learning activities based on the ' .
            'content below. Each activity must test different knowledge from the content.

ACTIVITY SEQUENCE (Fixed - generate in this exact order):
The standard sequence is: cardselect, columnsort, flashcards, matching, truefalseswipe, categorysort, fillinblank, ordering.
Generate activities in this sequence, cycling if more than 8 are requested:
' . self::js_str(implode('
', array_map([self::class, "js_str"], self::js_map($sequence, function ($type, $i) {
                return (self::js_str(self::js_add($i, 1)) .
                '. ' . self::js_str($type));
            })))) . '
You MUST generate exactly ' . self::js_str($activitycount) . ' activities in the types listed above. Do not substitute or ' .
        'invent activity types.

LANGUAGE: Generate ALL content text (titles, questions, labels, descriptions, item text, category names, column names, ' .
        'instructions) in ' . self::js_str($languagename) . '. JSON keys MUST remain in English. Only VALUES are in the ' .
            'target ' .
        'language.
SPELLING: ' . self::js_str($spellingrule) . '
' . self::js_str($contextlines) . '

CONTENT DISTRIBUTION MANDATE (CRITICAL):
Mentally divide the learning content below into ' . self::js_str($activitycount) . ' approximately equal sections from start ' .
        'to finish. Each activity must draw its core concept from a DIFFERENT section of the content. Activities must ' .
            'progress ' .
        'through the content in order — the first activity from the opening section, the last activity from the final ' .
            'section. ' .
        'Do NOT generate multiple activities from the opening paragraphs while ignoring later content. Every section of the ' .
        'content must be represented.

LEARNING CONTENT:
' . self::js_str($content));

        if ($scenariomode === true) {
            $isuniversityroute = $route === 'university';
            $scenariocontext = $isuniversityroute ? ('

SCENARIO MODE (CRITICAL):
Each activity MUST be framed within a realistic academic or professional practice scenario. Instead of generic questions ' .
                'about the content, create a short scenario (2-3 sentences) describing a specific university, research, ' .
                'clinical, or professional situation involving a named student, researcher, or practitioner, then ask the ' .
                'question within that context.

For example, instead of "What are the key principles of informed consent?", write a scenario like: "Priya is a third-year ' .
                'nursing student preparing for her first clinical placement at a metropolitan hospital. Her clinical mentor ' .
                'asks her to explain the consent process before a patient procedure. What key principles should Priya ' .
                'outline?"

The scenario should feel authentic to academic and professional settings. Use different character names for each activity. ' .
                'Scenarios should involve realistic academic situations: research projects, clinical placements, laboratory ' .
                'work, fieldwork, group presentations, coursework submissions, professional practice, or peer collaboration.') :
                    ('

SCENARIO MODE (CRITICAL):
Each activity MUST be framed within a realistic workplace scenario. Instead of generic questions about the content, create a ' .
                    'short scenario (2-3 sentences) describing a specific workplace situation involving a named worker, then ' .
                    'ask the question within that context.

For example, instead of "Which PPE is required for welding?", write a scenario like: "Jake is about to start a welding job ' .
                    'on a construction site. His supervisor asks him to check he has the right protective equipment before ' .
                    'beginning. Which PPE should Jake ensure he is wearing?"

The scenario should feel authentic and help the learner apply knowledge to real situations. Use different character names ' .
                    'for each activity.');
            if (self::js_truthy($scenariocountry)) {
                $scenariocontext .= ('
SCENARIO COUNTRY: Place scenarios in ' . self::js_str($scenariocountry) . '. Do not invent a city, named organisation, site, ' .
                'local policy or jurisdiction-specific rule. Use a generic setting unless a region or location is supplied ' .
                    'in the ' .
                'learning content or teacher instructions.');
            }
            if (self::js_truthy($scenarioindustry)) {
                $scenariocontext .= ('
SCENARIO INDUSTRY: Keep scenarios within ' . self::js_str($scenarioindustry) . '. Use only settings, equipment, terminology ' .
                'and practices supported by the supplied learning content or teacher instructions; do not invent a named ' .
                    'employer, site, ' .
                'local policy or legal requirement.');
            }
            if (self::js_truthy($scenarioworkerlevel)) {
                $scenariocontext .= $isuniversityroute ? ('
SCENARIO LEARNER LEVEL: Every character in the scenarios must be at the "' . self::js_str($scenarioworkerlevel) . '" level. ' .
                'Explicitly mention their experience level in each scenario (e.g., "a first-year student", "a graduate ' .
                    'researcher"). ' .
                'Adjust the complexity, academic expectations, and language to match this level.') : ('
SCENARIO WORKER LEVEL: Every character in the scenarios must be at the "' . self::js_str($scenarioworkerlevel) . '" level. ' .
                'Explicitly mention their experience level in each scenario (e.g., "on her first week", "a new apprentice", ' .
                    '"a ' .
                'trainee"). Adjust the complexity, responsibilities, and language to match this experience level.');
            }
            $userprompt .= $scenariocontext;
        }

        $budget = max(4000, $activitycount * 500);
        try {
            $responsetext = (self::$aicall)(
                $systemprompt,
                $userprompt,
                ['maxtokens' => $budget, 'json' => true, 'temperature' => 0.7]
            );
        } catch (\moodle_exception $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new http_error(500, $e->getMessage() !== '' ? $e->getMessage() : 'Internal server error');
        }

        try {
            $parsed = self::parse($responsetext);
        } catch (\Throwable $e) {
            throw new http_error(500, 'Failed to parse AI response');
        }
        if ($parsed === null) {
            throw new http_error(500, "Cannot read properties of null (reading 'activities')");
        }
        $activities = is_array($parsed) && !array_is_list($parsed) ? ($parsed['activities'] ?? null) : null;
        if (!is_array($activities) || !array_is_list($activities) || count($activities) === 0) {
            throw new http_error(500, 'AI generated no activities');
        }
        foreach ($activities as $i => $act) {
            if ($act === null) {
                throw new http_error(500, "Cannot read properties of null (reading 'type')");
            }
            $isobject = is_array($act) && !array_is_list($act);
            if (!$isobject || !self::js_truthy($act['type'] ?? null) || !self::js_truthy($act['title'] ?? null)) {
                throw new http_error(500, 'Activity ' . ($i + 1) . ' missing type or title');
            }
        }

        if ($voiceoverenabled === true) {
            $activities = self::narrate($activities, $language, self::js_truthy($voiceid) ? $voiceid : 'Zephyr');
        }

        return ['ok' => true, 'activities' => $activities, 'creditsUsed' => 0, 'remainingCredits' => 'unlimited'];
    }

    /**
     * POST /api/aiactivities-regenerate-audio.
     *
     * @param mixed $body The request body.
     * @return array The route's answer.
     * @throws http_error When the route answers with an error status.
     */
    public static function regenerate_audio($body): array {
        $body = is_array($body) && !array_is_list($body) ? $body : [];
        if (!self::js_truthy($body['siteId'] ?? null) || !self::js_truthy($body['apiKey'] ?? null)) {
            throw new http_error(401, 'Missing credentials');
        }
        $activities = $body['activities'] ?? null;
        if (!is_array($activities) || !array_is_list($activities) || count($activities) === 0) {
            throw new http_error(400, 'Activities required');
        }
        $language = self::js_truthy($body['voiceLanguage'] ?? null) ? $body['voiceLanguage'] : 'en-AU';
        $voiceid = self::js_truthy($body['voiceId'] ?? null) ? $body['voiceId'] : 'Zephyr';
        $voice = self::voice_config($language, $voiceid);
        $updated = [];
        foreach ($activities as $act) {
            $text = self::narration_text($act, 'Failed to regenerate audio');
            $audiodata = '';
            if (self::len($text) > 0) {
                $audiodata = self::speak($text, $voice);
            }
            $copy = is_array($act) && !array_is_list($act) ? $act : self::spread($act);
            $copy['audioData'] = $audiodata;
            $updated[] = $copy;
        }
        return ['ok' => true, 'activities' => $updated];
    }

    /**
     * Narrate generated activities: one clip per activity, of its question (or title), as the generate route does.
     *
     * @param array $activities
     * @param mixed $language
     * @param mixed $voiceid
     * @return array The activities with audioData set.
     */
    public static function narrate(array $activities, $language, $voiceid): array {
        $voice = self::voice_config($language, $voiceid);
        foreach ($activities as $i => $act) {
            $text = self::narration_text($act, '(act.question || act.title || "").trim is not a function');
            $activities[$i]['audioData'] = self::len($text) > 0 ? self::speak($text, $voice) : '';
        }
        return $activities;
    }

    /**
     * The text the service narrates for an activity: ((act.question || act.title) || '').trim().
     *
     * @param mixed $act
     * @param string $error What the route answers when the text is not a string.
     * @return string
     */
    protected static function narration_text($act, string $error): string {
        if ($act === null) {
            throw new http_error(500, $error);
        }
        $isobject = is_array($act) && !array_is_list($act);
        $q = $isobject ? ($act['question'] ?? null) : null;
        $t = $isobject ? ($act['title'] ?? null) : null;
        $text = self::js_truthy($q) ? $q : (self::js_truthy($t) ? $t : '');
        if (!is_string($text)) {
            throw new http_error(500, $error);
        }
        return self::jtrim($text);
    }

    /**
     * One narration clip, base64, or '' when the voice fails (the service carries on without it).
     *
     * @param string $text
     * @param array $voice
     * @return string
     */
    protected static function speak(string $text, array $voice): string {
        try {
            $audio = (self::$tts)($text, $voice);
        } catch (\Throwable $e) {
            return '';
        }
        return strlen((string) $audio) > 0 ? base64_encode($audio) : '';
    }

    /**
     * An object spread of a non-object value ({...x}), as the regenerate route builds each activity.
     *
     * @param mixed $v
     * @return array
     */
    protected static function spread($v): array {
        if (is_string($v)) {
            $out = [];
            foreach (preg_split('//u', $v, -1, PREG_SPLIT_NO_EMPTY) as $i => $ch) {
                $out[(string) $i] = $ch;
            }
            return $out;
        }
        if (is_array($v)) {
            $out = [];
            foreach ($v as $i => $x) {
                $out[(string) $i] = $x;
            }
            return $out;
        }
        return [];
    }

    /**
     * learningActivityDurationGuidance().
     *
     * @param int $count
     * @param int|null $sessionminutes
     * @param int $activityminutes
     * @return string
     */
    public static function duration_guidance(int $count, ?int $sessionminutes, int $activityminutes): string {
        if ($sessionminutes !== null) {
            $minutes = self::js_round($sessionminutes / $count * 10) / 10;
            return 'The session lasts ' . $sessionminutes . ' minutes and has ' . $count . ' activities. Aim for about '
                . self::num($minutes) . ' minutes per activity, including reading, trying, feedback and reflection.';
        }
        return 'Each activity should take about ' . $activityminutes . ' minutes to complete.';
    }

    /**
     * A number as JavaScript prints it in a template (shortest round-trip form).
     *
     * @param float $x
     * @return string
     */
    protected static function num(float $x): string {
        if (floor($x) == $x && abs($x) < 1e15) {
            return (string) (int) $x;
        }
        for ($p = 1; $p < 17; $p++) {
            $s = sprintf('%.' . $p . 'G', $x);
            if ((float) $s === $x) {
                return $s;
            }
        }
        return sprintf('%.17G', $x);
    }

    /**
     * Math.round().
     *
     * @param float $x
     * @return float
     */
    protected static function js_round(float $x): float {
        return floor($x + 0.5);
    }

    /**
     * The service's request schema (zod), with zod's own messages.
     *
     * @param array $b The request body.
     * @return array|string The parsed values with defaults, or the error text.
     */
    public static function validate(array $b) {
        if (array_is_list($b) && $b !== []) {
            $b = [];
        }
        $issues = [];
        $out = [];
        $fields = [
            'siteId' => ['string', 1, null, null],
            'apiKey' => ['string', 1, null, null],
            'content' => ['string', 50, 50000, null],
            'language' => ['string', null, null, 'en-AU'],
            'activityCount' => ['int', 3, 20, 5],
            'sessionMinutes' => ['int', 1, 1440, false],
            'activityMinutes' => ['int', 1, 20, 4],
            'scenarioMode' => ['bool', null, null, false],
            'scenarioCountry' => ['string', null, null, false],
            'scenarioIndustry' => ['string', null, null, false],
            'scenarioWorkerLevel' => ['string', null, null, false],
            'route' => ['string', null, null, 'vet'],
            'spelling' => ['string', null, 80, ''],
            'country' => ['string', null, 80, ''],
            'state' => ['string', null, 80, ''],
            'industry' => ['string', null, 120, ''],
            'jobLevel' => ['string', null, 120, ''],
            'teacherNotes' => ['string', null, 4000, ''],
            'voiceoverEnabled' => ['bool', null, null, false],
            'voiceGender' => ['string', null, null, 'female'],
            'voiceId' => ['string', null, null, 'Zephyr'],
        ];
        foreach ($fields as $name => [$type, $min, $max, $default]) {
            if (!array_key_exists($name, $b)) {
                if ($default === null) {
                    $issues[] = $name . ': Required';
                } else {
                    $out[$name] = $default === false ? null : $default;
                }
                continue;
            }
            $value = $b[$name];
            $received = self::zod_type($value);
            if ($type === 'string') {
                if (!is_string($value)) {
                    $issues[] = $name . ': Expected string, received ' . $received;
                    continue;
                }
                $len = self::len($value);
                if ($min !== null && $len < $min) {
                    $issues[] = $name . ': String must contain at least ' . $min . ' character(s)';
                }
                if ($max !== null && $len > $max) {
                    $issues[] = $name . ': String must contain at most ' . $max . ' character(s)';
                }
            } else if ($type === 'bool') {
                if (!is_bool($value)) {
                    $issues[] = $name . ': Expected boolean, received ' . $received;
                    continue;
                }
            } else {
                if (!is_int($value) && !is_float($value)) {
                    $issues[] = $name . ': Expected number, received ' . $received;
                    continue;
                }
                if (is_float($value) && floor($value) != $value) {
                    $issues[] = $name . ': Expected integer, received float';
                }
                if ($value < $min) {
                    $issues[] = $name . ': Number must be greater than or equal to ' . $min;
                }
                if ($value > $max) {
                    $issues[] = $name . ': Number must be less than or equal to ' . $max;
                }
                $value = (int) $value;
            }
            $out[$name] = $value;
        }
        return $issues ? implode(', ', $issues) : $out;
    }

    /**
     * The type name zod reports for a value it did not expect.
     *
     * @param mixed $v
     * @return string
     */
    protected static function zod_type($v): string {
        if ($v === null) {
            return 'null';
        }
        if (is_bool($v)) {
            return 'boolean';
        }
        if (is_int($v) || is_float($v)) {
            return 'number';
        }
        if (is_string($v)) {
            return 'string';
        }
        if (is_array($v) && array_is_list($v)) {
            return 'array';
        }
        return 'object';
    }

    /**
     * getVoiceConfigWithId(): the Google voice for a language and voice id.
     *
     * @param mixed $language
     * @param mixed $voiceid
     * @return array languageCode, name, id.
     */
    public static function voice_config($language, $voiceid): array {
        $valid = ['Aoede', 'Kore', 'Leda', 'Zephyr', 'Puck', 'Charon', 'Fenrir', 'Orus'];
        $voice = in_array($voiceid, $valid, true) ? $voiceid : 'Aoede';
        $fallbacks = [
            'ms-MY' => ['ms-MY', 'ms-MY-Standard-D'],
            'pa-IN' => ['hi-IN', 'hi-IN-Chirp3-HD-' . $voice],
            'fil-PH' => ['fil-PH', 'fil-PH-Standard-A'],
            'yue-HK' => ['yue-HK', 'yue-HK-Standard-D'],
            'cmn-TW' => ['cmn-CN', 'cmn-CN-Chirp3-HD-' . $voice],
            'pt-PT' => ['pt-BR', 'pt-BR-Chirp3-HD-' . $voice],
            'ca-ES' => ['es-ES', 'es-ES-Chirp3-HD-' . $voice],
            'is-IS' => ['is-IS', 'is-IS-Standard-A'],
        ];
        $key = self::key_string($language);
        if (isset($fallbacks[$key])) {
            return ['languageCode' => $fallbacks[$key][0], 'name' => $fallbacks[$key][1], 'id' => $voice];
        }
        $code = self::VOICE_LANGUAGES[$key] ?? 'en-AU';
        return ['languageCode' => $code, 'name' => $code . '-Chirp3-HD-' . $voice, 'id' => $voice];
    }

    /** Google voice language codes (getVoiceConfigWithId). */
    public const VOICE_LANGUAGES = [
        'en-AU' => 'en-AU',
        'en-GB' => 'en-GB',
        'en-IN' => 'en-IN',
        'en-US' => 'en-US',
        'es-ES' => 'es-ES',
        'es-US' => 'es-US',
        'fr-CA' => 'fr-CA',
        'fr-FR' => 'fr-FR',
        'de-DE' => 'de-DE',
        'pt-BR' => 'pt-BR',
        'nl-BE' => 'nl-BE',
        'nl-NL' => 'nl-NL',
        'da-DK' => 'da-DK',
        'fi-FI' => 'fi-FI',
        'nb-NO' => 'nb-NO',
        'sv-SE' => 'sv-SE',
        'bg-BG' => 'bg-BG',
        'cs-CZ' => 'cs-CZ',
        'hr-HR' => 'hr-HR',
        'hu-HU' => 'hu-HU',
        'pl-PL' => 'pl-PL',
        'ro-RO' => 'ro-RO',
        'ru-RU' => 'ru-RU',
        'sk-SK' => 'sk-SK',
        'sl-SI' => 'sl-SI',
        'sr-RS' => 'sr-RS',
        'uk-UA' => 'uk-UA',
        'et-EE' => 'et-EE',
        'lt-LT' => 'lt-LT',
        'lv-LV' => 'lv-LV',
        'el-GR' => 'el-GR',
        'it-IT' => 'it-IT',
        'cmn-CN' => 'cmn-CN',
        'ja-JP' => 'ja-JP',
        'ko-KR' => 'ko-KR',
        'id-ID' => 'id-ID',
        'th-TH' => 'th-TH',
        'vi-VN' => 'vi-VN',
        'bn-IN' => 'bn-IN',
        'gu-IN' => 'gu-IN',
        'hi-IN' => 'hi-IN',
        'kn-IN' => 'kn-IN',
        'ml-IN' => 'ml-IN',
        'mr-IN' => 'mr-IN',
        'ta-IN' => 'ta-IN',
        'te-IN' => 'te-IN',
        'ur-IN' => 'ur-IN',
        'ar-XA' => 'ar-XA',
        'he-IL' => 'he-IL',
        'tr-TR' => 'tr-TR',
        'sw-KE' => 'sw-KE',
    ];

    /**
     * JSON.parse(), with objects as arrays (empty objects stay objects).
     *
     * @param string $s
     * @return mixed
     * @throws \JsonException When the text is not JSON.
     */
    public static function parse(string $s) {
        return self::tree(json_decode($s, false, 512, JSON_THROW_ON_ERROR));
    }

    /**
     * Turn decoded JSON objects into arrays, keeping empty objects as objects.
     *
     * @param mixed $v
     * @return mixed
     */
    protected static function tree($v) {
        if ($v instanceof \stdClass) {
            $vars = get_object_vars($v);
            if (!$vars) {
                return new \stdClass();
            }
            $out = [];
            foreach ($vars as $k => $x) {
                $out[$k] = self::tree($x);
            }
            return $out;
        }
        if (is_array($v)) {
            return array_map([self::class, 'tree'], $v);
        }
        return $v;
    }

    /**
     * String.prototype.trim().
     *
     * @param string $s
     * @return string
     */
    public static function jtrim(string $s): string {
        return preg_replace('/^[' . self::WS . ']+|[' . self::WS . ']+$/u', '', $s);
    }

    /**
     * String length in UTF-16 code units, as JavaScript counts it.
     *
     * @param string $s
     * @return int
     */
    public static function len(string $s): int {
        return (int) (strlen(mb_convert_encoding($s, 'UTF-16LE', 'UTF-8')) / 2);
    }

    /**
     * The key a value names in an object lookup.
     *
     * @param mixed $v
     * @return string
     */
    protected static function key_string($v): string {
        return self::js_str($v);
    }

    // JavaScript semantics used by the ported prompt templates (generated with the port).

    /**
     * Js truthy.
     *
     * @param mixed $v
     * @return bool JavaScript truthiness.
     */
    private static function js_truthy($v): bool {
        if (is_array($v) || is_object($v)) {
            return true;
        }
        if (is_float($v) && is_nan($v)) {
            return false;
        }
        return $v !== null && $v !== false && $v !== '' && $v !== 0 && $v !== 0.0;
    }

    /**
     * Js or.
     *
     * @param mixed $a
     * @param mixed $b
     * @return mixed a || b
     */
    private static function js_or($a, $b) {
        return self::js_truthy($a) ? $a : $b;
    }

    /**
     * Js and.
     *
     * @param mixed $a
     * @param mixed $b
     * @return mixed a && b
     */
    private static function js_and($a, $b) {
        return self::js_truthy($a) ? $b : $a;
    }

    /**
     * Js add.
     *
     * @param mixed $a
     * @param mixed $b
     * @return mixed a + b
     */
    private static function js_add($a, $b) {
        if ((is_int($a) || is_float($a)) && (is_int($b) || is_float($b))) {
            return $a + $b;
        }
        return self::js_str($a) . self::js_str($b);
    }

    /**
     * Js str.
     *
     * @param mixed $v
     * @return string String(v)
     */
    private static function js_str($v): string {
        if ($v === null) {
            return 'null';
        }
        if ($v === true) {
            return 'true';
        }
        if ($v === false) {
            return 'false';
        }
        if (is_float($v)) {
            if (floor($v) == $v && abs($v) < 1e15) {
                return (string) (int) $v;
            }
            return rtrim(rtrim(sprintf('%.15F', $v), '0'), '.');
        }
        if (is_array($v)) {
            if ($v === [] || array_keys($v) === range(0, count($v) - 1)) {
                return implode(',', array_map(function ($x) {
                    return $x === null ? '' : self::js_str($x);
                }, $v));
            }
            return '[object Object]';
        }
        return (string) $v;
    }

    /**
     * Js num.
     *
     * @param mixed $v
     * @return float Number(v)
     */
    private static function js_num($v) {
        if (is_int($v) || is_float($v)) {
            return $v;
        }
        if ($v === null || $v === false || (is_string($v) && trim($v) === '')) {
            return 0;
        }
        if ($v === true) {
            return 1;
        }
        return is_numeric(trim((string) $v)) ? trim((string) $v) + 0 : NAN;
    }

    /**
     * Js get.
     *
     * @param mixed $o
     * @param mixed $k
     * @return mixed o[k] or null
     */
    private static function js_get($o, $k) {
        if (is_array($o)) {
            return $o[$k] ?? null;
        }
        if (is_string($o) && is_int($k)) {
            return mb_substr($o, $k, 1);
        }
        return null;
    }

    /**
     * Js len.
     *
     * @param mixed $v
     * @return int .length
     */
    private static function js_len($v): int {
        if (is_array($v)) {
            return count($v);
        }
        return strlen(mb_convert_encoding(self::js_str($v), 'UTF-16LE', 'UTF-8')) / 2;
    }

    /**
     * Js includes.
     *
     * @param mixed $h
     * @param mixed $n
     * @return bool .includes()
     */
    private static function js_includes($h, $n): bool {
        if (is_array($h)) {
            return in_array($n, $h, true);
        }
        return strpos(self::js_str($h), self::js_str($n)) !== false;
    }

    /**
     * Js slice.
     *
     * @param mixed $v
     * @param int $a
     * @param int|null $b
     * @return mixed .slice() on strings (UTF-16) and arrays
     */
    private static function js_slice($v, $a = 0, $b = null) {
        if (is_array($v)) {
            $n = count($v);
            $a = $a < 0 ? max(0, $n + $a) : min($a, $n);
            $b = $b === null ? $n : ($b < 0 ? max(0, $n + $b) : min($b, $n));
            return array_slice($v, $a, max(0, $b - $a));
        }
        $u = mb_convert_encoding(self::js_str($v), 'UTF-16LE', 'UTF-8');
        $n = strlen($u) / 2;
        $a = $a < 0 ? max(0, $n + $a) : min($a, $n);
        $b = $b === null ? $n : ($b < 0 ? max(0, $n + $b) : min($b, $n));
        return mb_convert_encoding(substr($u, $a * 2, max(0, $b - $a) * 2), 'UTF-8', 'UTF-16LE');
    }

    /**
     * Js map.
     *
     * @param mixed $v
     * @param callable $f
     * @return array .map()
     */
    private static function js_map($v, callable $f): array {
        $out = [];
        foreach (array_values((array) $v) as $i => $x) {
            $out[] = $f($x, $i);
        }
        return $out;
    }

    /**
     * Js filter.
     *
     * @param mixed $v
     * @param callable $f
     * @return array .filter()
     */
    private static function js_filter($v, callable $f): array {
        $out = [];
        foreach (array_values((array) $v) as $i => $x) {
            if (self::js_truthy($f($x, $i))) {
                $out[] = $x;
            }
        }
        return $out;
    }

    /**
     * Js typeof.
     *
     * @param mixed $v
     * @return string typeof
     */
    private static function js_typeof($v): string {
        if (is_string($v)) {
            return 'string';
        }
        if (is_int($v) || is_float($v)) {
            return 'number';
        }
        if (is_bool($v)) {
            return 'boolean';
        }
        return $v === null ? 'undefined' : 'object';
    }

    /**
     * Js replace first.
     *
     * @param mixed $s
     * @param string $a
     * @param string $b
     * @return string .replace(string, string)
     */
    private static function js_replace_first($s, $a, $b): string {
        $s = self::js_str($s);
        $p = strpos($s, $a);
        return $p === false ? $s : substr_replace($s, $b, $p, strlen($a));
    }
}
