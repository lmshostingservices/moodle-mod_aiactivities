# AI Learning Activities (mod_aiactivities)

Turns pasted learning content into a sequence of interactive revision activities that students
work through on a phone, tablet or desktop.

Eight activity types are generated: Put in order, Sort into categories, Sort into columns,
Select the correct answer, Match the pairs, Flashcards, True or false, and Fill in the blank.
Optional scenario mode wraps every activity in a workplace context, and optional AI narration
reads each activity aloud.

## Requirements

* Moodle 4.4 or later (supported through 5.2, MySQL/MariaDB and PostgreSQL).
* PHP 8.1 or later.
* An account with the AI Grader generation service, which supplies a site ID and API key.

## What this plugin sends to an external service

Activity generation is not performed on your Moodle server. When a teacher generates activities,
this plugin sends the following to the generation service configured in the plugin settings
(`https://lms-labs.com` by default):

* the learning content the teacher pasted into the form;
* the chosen language, and the number of activities requested;
* the optional scenario settings (country, industry, sector, worker level, job roles);
* the optional narration settings (whether narration is wanted, and which voice);
* the site ID and API key that identify the subscription.

**No student data is ever sent.** Student progress is stored only in your Moodle database.
This is declared through Moodle's Privacy API, and appears in your site's data registry.

Teachers' browsers also keep the values last used in the generation form in `sessionStorage`,
so that the form is not empty when regenerating. That data never leaves the teacher's browser
and is discarded when the browser tab closes.

Generated activities, including any narration audio, are stored in your Moodle database and are
included in course backups.

## Installation

1. Install the ZIP through *Site administration ▸ Plugins ▸ Install plugins*, or unzip it into
   `mod/aiactivities` in your Moodle directory.
2. Visit *Site administration ▸ Notifications* to complete the database upgrade.
3. Go to *Site administration ▸ Plugins ▸ Activity modules ▸ AI Learning Activities* and enter
   your site ID and API key.

There are no post-installation steps beyond entering the credentials, and no Composer or build
step is required.

## Configuration

| Setting | Description |
| --- | --- |
| API URL | The base URL of the generation service. Leave at the default unless you have been given a different endpoint. |
| Site ID | The identifier issued with your subscription. |
| API key | The secret issued with your subscription. Stored write-only. |

If the optional `local_aiconfig` plugin (AI Grader Central Config) is installed, it holds the
site ID and API key centrally for every AI Grader plugin and takes priority over the values
stored here. `local_aiconfig` is optional: this plugin works without it.

## Credits

Generation consumes credits from your subscription: 2 credits per activity, or 3 per activity
when narration is enabled. The current balance is shown at the top of the activity page for
users who can generate content.

## Capabilities

| Capability | Purpose | Default roles |
| --- | --- | --- |
| `mod/aiactivities:addinstance` | Add the activity to a course | Editing teacher, Manager |
| `mod/aiactivities:view` | View and complete the activities | Student, Teacher, Editing teacher, Manager |
| `mod/aiactivities:create` | Generate, edit and regenerate activity content | Editing teacher, Manager |

## Completion

The activity provides one custom completion rule, *Student must complete all activities
correctly*. Students have unlimited attempts; completion is recorded once every activity in the
set has been answered correctly.

## Privacy

Student attempt data (progress, completion state and score) is stored in the
`aiactivities_attempts` table and is exported and deleted through Moodle's Privacy API. Course
reset can delete all attempts for a course.

## Accessibility

All activity types can be completed with a keyboard as well as with a mouse or touch. Results
are announced through a live region, narration can be stopped, and animations are suppressed
when the browser requests reduced motion.

## Support and issues

Please report bugs and feature requests on the issue tracker listed on the plugin's Moodle
Marketplace page.

## Licence

GNU GPL v3 or later. See `LICENSE`.
