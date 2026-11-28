# AI Learning Activities and the EU AI Act

Provider's technical analysis by LMS Labs (LMS Hosting Services), 28 November 2026. This is not legal advice. It covers `mod_aiactivities` 1.13.0. Every product claim below was checked against the code in this release.

## 1. Short answer

AI Learning Activities is an **AI system** (Article 3(1)) that generates **synthetic text, and optionally synthetic speech**, for practice activities. It is **not a high-risk system** for its intended purpose: the AI does not evaluate learners, steer their learning path, decide admission or monitor tests. Learners' answers are checked by fixed code against the answer key a teacher released. The duties that apply are the **Article 50 transparency duties** for generated content, and **AI literacy** (Article 4). Release 1.13.0 adds features that support both, plus human oversight that goes beyond what the Act requires for this use.

## 2. What the AI does, and what it does not (checked in code)

| Step | How it works | AI? |
|---|---|---|
| Teacher pastes course content, picks language, count, types, optional scenario and narration | Form in `templates/teacher_interface.mustache` | No |
| Content and options are sent for generation | `ajax.php` builds the payload (`aiactivities_build_generation_payload`): content, language, count, types, scenario fields, voice, site ID and key. **No learner data.** With Bring your own AI the same payload goes to the site's own provider (`classes/local/byok/engine.php`) | Yes (LMS Labs service or the site's own AI) |
| Activities (questions, options, answer keys, explanations) are written | LMS Labs generation service, or the site's own AI | **Yes: synthetic text** |
| Optional narration | Text-to-speech, stored as audio on each activity | **Yes: synthetic audio** |
| Teacher reviews, edits, removes, releases | Teacher interface, `releaseset` action, `classes/local/responsible_ai.php` | No |
| Learner answers are marked | `amd/src/player.js` compares the answer with the stored key; `ajax.php` `saveprogress`/`complete` | No (fixed rules) |
| Completion ("all correct") | `classes/completion/custom_completion.php` | No |
| Grades | None: `FEATURE_GRADE_HAS_GRADE` is false | n/a |

## 3. Classification

| Use case | Classification | Reason |
|---|---|---|
| Writing practice activities and narration from teacher content | Limited risk: Article 50 transparency | Generates synthetic text and audio (Art 50(2)). Content is reviewed by a teacher before learners see it (on by default) |
| Annex III 3(b) evaluating learning outcomes / steering learning | Not applicable for the intended purpose | The AI does not evaluate learners. Marking is rule-based against a teacher-released key. No grade is written. Completion uses the same fixed check |
| Annex III 3(a) admission, 3(c) level of education | Not applicable | No access, placement or level decisions |
| Annex III 3(d) monitoring prohibited behaviour in tests | Not applicable | No proctoring or detection |
| Article 5 prohibited practices (e.g. emotion recognition of learners) | Not present | None built or planned |
| Article 50(1) AI interacting with people | Not applicable | Learners do not converse with an AI |
| Article 50(4) deepfakes / AI text published on matters of public interest | Not applicable for normal use | Practice activities for enrolled learners; teacher review and editorial responsibility in any case |

**Misuse to avoid (instructions for use):** do not use completion of these activities as the sole evidence for a competency decision or a recorded result without your own assessment; do not paste learner personal data into the content box. If a deployer uses the output as a recorded assessment result, they should treat it as their own assessment instrument and check it accordingly. Human involvement alone does not take a system out of Annex III scope (draft Commission guidelines, 19 May 2026), so if LMS Labs ever adds AI marking or AI-chosen learning paths to this plugin, this analysis must be redone first.

## 4. Dates (re-checked 28 November 2026)

| Duty | Applies from |
|---|---|
| Prohibitions; AI literacy (Art 4, now "take measures") | 2 February 2025 |
| General-purpose AI model duties | 2 August 2025 |
| Article 50 transparency (general) | 2 August 2026 |
| Art 50(2) machine-readable marking for systems already on the market before 2 August 2026 | **2 December 2026** |
| New Art 5 prohibitions (non-consensual intimate material, CSAM) | 2 December 2026 |
| Annex III high-risk duties (includes education, point 3) | 2 December 2027 |
| Annex I (product safety) high-risk | 2 August 2028 |

The Digital Omnibus on AI (Regulation (EU) 2026/1744) entered into force on 27 July 2026 and moved the high-risk dates. The Code of Practice on Transparency of AI-Generated Content was published on 10 June 2026 and the final Article 50 guidelines on 20 July 2026. AI Learning Activities was on the market before 2 August 2026, so its Art 50(2) marking date is 2 December 2026; 1.13.0 ships the marking ahead of it.

## 5. Who does what

**LMS Labs (provider of the plugin and the generation service)**

- Art 50(2): mark generated text and audio in a machine-readable, detectable way. *Done in the plugin for the page* (section 6). *Outstanding in the service*: embed provenance in the narration audio files themselves (e.g. C2PA or audio watermark) and keep a generation record per request ID. Tracked as a service task.
- Art 4: give deployers what they need for AI literacy. *Done*: "Using AI responsibly" panel, learner "About AI here", this document.
- Keep this classification under review when features change (e.g. any AI marking would trigger Annex III 3(b) work before 2 December 2027).
- GPAI: obtain and keep the model provider's Art 53(1)(b) information for the models the service uses.

**The school or RTO (deployer)**

- Use the plugin as described here: check every set before release (the default), keep the learner notice on, and do not paste learner personal data.
- Art 50(4) only if you publish AI text to inform the public on matters of public interest (not the normal use); teacher review gives editorial responsibility in any case.
- Art 4: make sure staff who generate activities understand the limits (the in-product panel helps).
- Keep the Moodle logs for as long as your own policy requires; we suggest at least six months.

## 6. Feature matrix (1.13.0)

| Duty or good practice | Article | Feature | Where |
|---|---|---|---|
| Mark AI-generated content in a machine-readable way | 50(2) | Every learner page carries a schema.org `LearningResource` JSON-LD marker with IPTC `digitalSourceType` `trainedAlgorithmicMedia`, creation date and the reviewer; the player root carries `data-ai-generated`, `data-ai-digital-source-type`, `data-ai-reviewed` and `data-ai-narration`. The marker cannot be switched off | `templates/ai_notice.mustache`, `templates/player.mustache`, `responsible_ai::notice_context()` |
| Tell people content is AI-generated | 50 (transparency, Code of Practice labelling) | "Made with AI" notice, naming the teacher who checked the set, with "About AI here"; "The narration is an AI voice" when narrated. On by default (`learnernotice`) | Learner view |
| Human oversight before use | 14 (good practice here), 26(1) | "Check before release": new and regenerated sets stay hidden from learners until a teacher ticks "I have checked every question, answer and explanation" and releases them. On by default (`requirereview`). Learners' AJAX calls are refused until then | Teacher view, `ajax.php` `releaseset`, `view.php` |
| Ability to stop the system | 14(4)(e), 26(5) | Site-wide "Pause AI generation" for administrators, also respected by AI Course Builder. Released activities keep working | Plugin settings |
| Record keeping | 12 (good practice here) | Moodle log events: *Activities generated with AI* (count, engine, language, narration, review required), *AI-generated activities released to learners*, *Activity removed from a generated set* | `classes/event/` |
| AI literacy | 4 | "Using AI responsibly" panel on the generation form: responsibility, limits, fairness, data, how to report | Teacher view |
| Data minimisation and privacy | GDPR | No learner data sent to any AI. The reviewer's identity is covered by the privacy API (export and delete; the set stays released) | `classes/privacy/provider.php` |

## 7. Instructions for use (summary for deployers)

- **Intended purpose:** create formative practice activities, and optional narration, from course content a teacher supplies.
- **Not intended for:** marking or grading learners, deciding admission, placement or competency, or monitoring tests.
- **Known limits:** AI can state wrong facts confidently, mark a wrong option as correct, miss the most important point, use examples that do not suit your learners, or favour one variety of English. Non-English output may be less reliable. Narration can mispronounce names and technical terms.
- **Human oversight:** preview every activity, edit or remove anything wrong, then release. Regenerating takes the set away from learners again until it is checked.
- **Stopping:** an administrator can pause AI generation in *Site administration ▸ Plugins ▸ Activity modules ▸ AI Learning Activities ▸ Responsible AI*.
- **Telling learners:** keep "Tell learners about AI" on. It names the teacher who checked the set.
- **Logs:** see *Course ▸ Reports ▸ Logs*, filtered by the activity.

## 8. Provider to-do (LMS Labs)

1. Generation service: add provenance to narration audio files (C2PA manifest or audio watermark), and return a request ID with each generation so the plugin can log it.
2. Keep model suppliers' Art 53(1)(b) documentation on file for the models the service uses.
3. Re-check this analysis before adding any AI marking, adaptive sequencing or learner analytics (Annex III 3(b), from 2 December 2027).
4. Re-check the Article 50 guidelines and Code of Practice each release.

## 9. Sources

- Regulation (EU) 2024/1689 (AI Act), Articles 3(1), 4, 5, 6, 14, 26, 50, Annex III point 3. EUR-Lex.
- Regulation (EU) 2026/1744 (Digital Omnibus on AI), in force 27 July 2026: https://www.whitecase.com/insight-alert/eu-ai-omnibus-enters-force-amending-ai-act and https://cdp.cooley.com/digital-ai-omnibus-delays-key-deadlines-introduces-new-rules/
- Article 50(2) grace period to 2 December 2026 for systems already on the market: https://www.orrick.com/en/Insights/2026/07/EU-AI-Act-Update-Digital-Omnibus-Finalizes-8-Compliance-Changes
- European Commission, Code of Practice on Transparency of AI-Generated Content (10 June 2026): https://digital-strategy.ec.europa.eu/en/faqs/code-practice-transparency-ai-generated-content
- European Commission, Guidelines on the transparency obligations under Article 50 (final, 20 July 2026): https://digital-strategy.ec.europa.eu/policies/guidelines-transparency-ai-generated-content
- European Commission, draft guidelines on the classification of high-risk AI systems (19 May 2026): https://digital-strategy.ec.europa.eu/en/library/draft-guidelines-high-risk
- IPTC Digital Source Type vocabulary: http://cv.iptc.org/newscodes/digitalsourcetype/
