# LMS Plugin

## Purpose
This plugin provides the independent LMS admin foundation for Z4Platform.

## Scope
- Adds an admin menu named `LMS`.
- Adds placeholder pages for:
  - Overview
  - Courses
  - Lessons
  - Students
  - Settings

## Payment Boundary
Electronic payment is intentionally out of scope for this plugin.

Payment must remain in a separate `Payment` plugin.

## Current Version
`1.5.11`

## Current Implementation
- Course, curriculum, lesson, student, instructor, enrollment, and progress management.
- Inline lesson editor modal inside the course builder; a new course draft is created only when the lesson is saved.
- Per-course repeatable “What You Will Learn” and FAQ fields rendered on the public course page.
- Platform Media Studio integration for course media, lesson media and files, and LMS profile images. Uploading and selecting existing media use the platform's canonical media controller through plugin-owned bridges.
- Frontend course catalog, course details, player, student portal, and instructor portal.
- Existing platform users can request instructor access. Administrators can approve the request or grant instructor access directly by selecting an existing user.
- Approved instructors can create courses, diplomas, and lessons from the frontend account area. Each instructor has an explicit publishing policy controlled from LMS administration; review is required by default, while direct publishing must be enabled deliberately.
- LMS-owned notifications report instructor applications and content submissions to administrators and return review decisions to instructors.
- The course-player header includes live LMS notifications and a persistent light/dark mode control.
- LMS-owned template studio for editable course-list and course-details HTML, CSS, and JavaScript.
- An active-plugin Page Builder hook registers the `LMS Courses` element. Disabling LMS removes its element, editor asset, and frontend renderer without modifying Page Builder.
- Course-list elements support template selection, filtering, ordering, pagination, responsive columns, grid/card, and horizontal-slider layouts.
- Locally selected and directly uploaded lesson videos are stored in private LMS storage and streamed through a temporary signed route that checks enrollment on every request. Existing public LMS lesson uploads are migrated to private storage during the update. The custom player suppresses native download, remote playback, picture-in-picture, context menus, and direct provider interaction.
- YouTube lessons use the privacy-enhanced `youtube-nocookie.com` player with native controls, keyboard interaction, fullscreen, related videos, and direct iframe interaction disabled. Browser-level inspection and capture cannot be prevented by any web player, so this is presentation hardening rather than a false DRM guarantee.
- PeerTube lesson and promotional-video links support short `/w/{id}`, watch `/videos/watch/{id}`, and embed `/videos/embed/{id}` URLs from any PeerTube instance. The LMS normalizes them to the official embed endpoint.
- PeerTube lesson iframes are non-interactive and expose no provider link. The LMS-owned controls communicate with the player API for playback, seeking, volume, speed, captions, fullscreen, duration, and progress. Promotional-video modals retain native controls but hide PeerTube titles and instance links.
- Lesson availability is enforced per lesson: immediate, scheduled by date, or unlocked by an explicit prerequisite lesson from the same course.
- Published free courses are enrolled automatically for active LMS students. Published course owners receive an owner enrollment so their programs also appear in their learning account.
- Free-course and new-lesson messages are written to the LMS notification center immediately and to a persistent email outbox. Run Laravel's scheduler every minute; `lms:dispatch-email-notifications` enforces the hard limit of five sent messages per rolling ten-minute window.

## Permissions
- `lms.view`
- `lms.courses.view`
- `lms.lessons.view`
- `lms.students.view`
- `lms.instructors.view`
- `lms.instructors.manage`
- `lms.student.portal`
- `lms.instructor.portal`
- `lms.instructor.courses.manage`
- `lms.instructor.lessons.manage`
- `lms.settings.manage`

## Admin Routes
- `admin.plugins.lms.overview`
- `admin.plugins.lms.courses`
- `admin.plugins.lms.lessons`
- `admin.plugins.lms.students`
- `admin.plugins.lms.instructors`
- `admin.plugins.lms.instructor-requests.index`
- `admin.plugins.lms.notifications`
- `admin.plugins.lms.templates.index`
