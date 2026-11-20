# LMS Changelog

## 2.1.15 - 2026-11-18

- Declared `lms.codes.generate` in `module.json` so the permission is registered automatically on install/update.
- Course Codes page no longer crashes when the permission does not exist yet (safe issuer lookup).

## 2.1.14 - 2026-11-18

- Students list: the student name is now a link to "Courses & Progress" so the admin can see every enrolled course, enrollment date and progress in one click.
## 2.1.13 - 2026-11-17

- Carried the production hotfixes into the module:
  - `partners` is cast to array (fixes 500 on Save draft / Publish).
  - Learning outcomes render through `Course::outcomeLabels()` in course details and player (supports both string and {title,text} shapes).
## 2.1.12 - 2026-11-17

- Course Codes: the `#USER-{id}` value is now a link that opens the admin users screen filtered by the user's email; hovering shows the user's name and email.
## 2.1.11 - 2026-11-17

- Course list and Total Students stat no longer count the instructor's own enrollment (`instructor_owner`).
- Only real students are counted per course and in the dashboard total.
## 2.1.10 - 2026-11-16

- The Course Codes account tab now sits directly under Instructor in the account sidebar (order 31).
## 2.1.9 - 2026-11-16

- Users holding `lms.codes.generate` get a Course Codes section inside /account where they can generate codes (1-100), see their latest codes and export their own codes as CSV.
- The account section is hidden for everyone else.
## 2.1.8 - 2026-11-16

- Code issuers panel: correct Name/Email/Action headers, no horizontal scrolling, and a compact no-wrap "Revoke access" button with a trash icon.
## 2.1.7 - 2026-11-16

- New `lms.codes.generate` permission: administrators can grant course-code issuing rights to any registered user from a panel inside Course Codes, and revoke them there.
- The Course Codes screen, code generation and export accept either `lms.settings.manage` or `lms.codes.generate`.
- Deactivating an instructor now removes the `instructor` role immediately (reactivating restores it), and deleting an instructor also stores the inactive status.
- Instructor search marks users linked to a soft-deleted instructor record, and the form explains that saving restores that record.
## 2.1.6 - 2026-11-16

- The Course Codes export button is hidden until at least one code is selected, then exports exactly the selected codes.
## 2.1.5 - 2026-11-16

- The Course Codes export button uses an inline SVG icon, so the download glyph is always visible regardless of icon-library timing.
## 2.1.4 - 2026-11-16

- The Course Codes generator now lists real course titles instead of a broken placeholder string.
- The export button sits beside the codes table instead of the page header.
- Every code row has a checkbox with select-all support, and the export honours the selected rows (plus the course filter).
## 2.1.3 - 2026-11-16

- Course codes (coupon codes) can be exported to CSV from the Course Codes screen, honouring the selected course filter.
- The export contains the code, course title, batch, status, the student who used it (name and email), the used date and the creation date, with a UTF-8 BOM so Excel opens Arabic correctly.
## 2.1.2 - 2026-11-16

- Admin, instructor and storefront labels no longer show stray numbers from a broken translation pass (for example "View 40" and "Please review the highlighted fields. 49"); 26 strings were corrected in Arabic and English.
- The course short description now accepts up to 500 characters (was 200) in the admin builder, the instructor portal and the validation rules.
- The character counter and the field maxlength follow the new limit.
## 2.1.0 - 2026-11-11

- Merged the 2.0.0 media/attachment workflow with the 1.5.75 translation and SEO release.
- Restored LMS course and lesson translation tables, models, language files and SEO contributors.
- Restored language tabs in the admin course and lesson editors.
- Added language tabs and translation saving to the instructor course and lesson editors.
- Preserved translation tables in the uninstall manifest.

## 2.0.0 - 2026-11-11

- Consolidated release for the media/attachment workflow.
- Media Studio picker: non-image files (PDF, DOC, ZIP, video…) show the correct file-type icon instead of a broken image; images keep their previews.
- The upload panel shows the selected file name next to “Choose file”.
- Lesson attachments render as a single-line row with a clickable name, an inline preview (eye) button that opens the file in a new tab, and a × button that detaches it from the lesson only.
- Attachments picked before saving behave exactly like saved ones (same link and preview button).
- Fixed the attachment row grid so the preview button no longer breaks the layout.

## 1.5.79 - 2026-11-11

- Fixed the attachment row layout: the preview (eye) button now sits inline inside the file-name cell instead of breaking the row grid, and the file name truncates cleanly.

## 1.5.78 - 2026-11-11

- Attachments picked in the lesson editor (before saving) now also render with a clickable name and a preview (eye) button that opens the file in a new tab.

## 1.5.77 - 2026-11-11

- Saved lesson attachments now show a preview (eye) button next to the file name that opens the file in a new tab.

## 1.5.76 - 2026-11-11

- Saved lesson attachments in the standalone lesson editor now open in a new tab when the file name is clicked (previously the name was plain text with no preview).

## 1.5.75 - 2026-11-11

- Media Studio picker: non-image files (PDF, DOC, ZIP, video…) no longer render as broken images in the course/lesson media modal; they now show the correct file-type icon, while real images keep their previews.
- Lesson attachments in the course editor are now removable chips: the file name opens the attachment in a new tab, and an × button detaches it from the lesson only (the file stays available in the Media Studio library).
- The media upload panel now shows the selected file name next to “Choose file”, so it is clear which file will be uploaded before pressing Upload & Insert.

## 1.5.74 - 2026-10-18

- Validation errors are now located on the course builder: the field that failed is highlighted, its message is shown under it, and the builder jumps to the step that owns it. Pressing Publish also checks required fields before submitting and points at the first missing one.

## 1.5.73 - 2026-10-18

- The What You Will Learn description is now limited to 1000 characters (was 500) and line breaks are normalised before validation, so multi-line explanations no longer fail with a false length error.

## 1.5.72 - 2026-10-18

- Slug-based image fallback now searches media/courses, media, courses, lms/course-images and the public storage root, so restored course images work wherever they are uploaded.

## 1.5.71 - 2026-10-18

- Courses without a working image now fall back to media/courses/<course-slug>.(webp|jpg|jpeg|png), so legacy images restored from a backup can be dropped into that folder without touching the database.

## 1.5.70 - 2026-10-18

- Course images that no longer exist on disk now fall back to a clean placeholder instead of a broken icon, and the media route also looks inside public/ and storage/app for legacy files.

## 1.5.69 - 2026-10-18

- Course images stored as absolute /storage URLs are now served through the plugin media route when the file exists locally, so catalog images also work on sites that save full URLs in the media metadata.

## 1.5.68 - 2026-10-18

- The plugin media route now serves any existing file from the public disk (course images stored under lms/course-images included), not only files under media/, so every course image loads regardless of where it was uploaded.

## 1.5.67 - 2026-10-18

- Course images in the catalog and templates now resolve through the plugin media route (/lms/media-file/...) instead of /storage, so uploaded images display even when the storage symlink is unavailable.

## 1.5.66 - 2026-10-18

- Catalog card images now fill the whole card (object-fit: cover) instead of being letterboxed inside the media area.

## 1.5.65 - 2026-10-18

- Removed the Accreditation & Partners column from the About tab (and its form field), so the About content uses the full width unless the requirements or audience cards are filled.

## 1.5.64 - 2026-10-18

- New courses no longer start with default learning outcomes or FAQ entries: both lists begin empty and are filled with the Add row / Add FAQ buttons.

## 1.5.63 - 2026-10-18

- The Add row button for What You Will Learn now sits under the last row of the list instead of the section header.

## 1.5.62 - 2026-10-18

- What You Will Learn is now its own front-end tab, shown only when the course has learning outcomes. Each outcome has a title and a short explanation, rendered as cards; the FAQ tab stays conditional as before.
- The course form now edits each learning outcome as a title plus description.

## 1.5.61 - 2026-10-18

- Step grouping rebalanced: step 1 (Course details) now holds every field up to and including Course Description, step 2 (Extra content) holds What You Will Learn, Course FAQ and Advanced details, and step 3 holds the curriculum with the review panel.

## 1.5.60 - 2026-10-18

- The course builder action bar and the step bar no longer stick to the top while scrolling; they now scroll with the page.

## 1.5.59 - 2026-10-18

- The course wizard is now 3 steps: Basics, Content & pricing, and Curriculum & publish (curriculum plus the review summary and publish actions).

## 1.5.58 - 2026-10-18

- Phase 2: the course form is now a five-step wizard (Basics, Pricing & training, Content & media, Curriculum, Review & publish) with a sticky step bar and Back/Next footer.
- The Review step summarises title, program type, category, instructor, training type, both prices and units/lessons, and offers Save changes / Publish.
- The active step is remembered between reloads; after a validation error the form starts at step 1.

## 1.5.57 - 2026-10-18

- Phase 1 (part 2): the sidebar now has a single Publishing card (status, published date and one action) instead of two duplicated cards, plus a Ready to publish checklist that tracks the required fields live.

## 1.5.56 - 2026-10-18

- Phase 1 of the content restructure: a short Overview field now opens the About tab with a Read more toggle for the full description, and a new Accreditation & Partners field renders as chips.
- The curriculum header shows the number of units alongside lessons and total duration, and the description column is limited to a comfortable reading width.

## 1.5.55 - 2026-10-18

- About This Course now keeps the paragraph breaks of the rich-text description: block-level tags are converted to line breaks before the text is sanitised, and list items are kept as separate lines.

## 1.5.54 - 2026-10-17

- The course price now follows the student's training type: the price card shows the online or in-person price matching their choice, and it updates instantly when they switch the training type from the course page.

## 1.5.53 - 2026-10-17

- The About tab now renders only the sections that have content: What You Will Learn, Course Requirements and Who This Course Is For are omitted when empty, the About card stretches to the full width, and the FAQ tab and panel disappear when no questions are added. The empty placeholder text was removed.

## 1.5.52 - 2026-10-17

- Fixed images picked from Media Studio not being saved: plugin media URLs (/lms/media-file/...) are now resolved back to their storage path for course and lesson images.

## 1.5.51 - 2026-10-17

- Fixed the short description being rejected as longer than 200 characters when it contained line breaks: newlines are normalised before validation so the stored length matches the on-screen counter.

## 1.5.50 - 2026-10-17

- After a course is published the builder button becomes View Diploma / View Course and opens the public course page; unpublished courses keep the private preview.

## 1.5.49 - 2026-10-17

- Added a Paste text button as the first editor toolbar action: it inserts the clipboard text, and if the browser blocks clipboard reading it opens a paste prompt instead, so text can always be pasted into the course and lesson descriptions.

## 1.5.48 - 2026-10-17

- Save Changes on a draft course now stores what has been filled so far without validation, while Publish enforces every required field. Published courses keep full validation when saving changes.

## 1.5.47 - 2026-10-17

- The empty-library message is hidden as soon as a file is selected in Media Studio, so it no longer stays under the preview.

## 1.5.46 - 2026-10-17

- Media Studio shows a live preview of the selected image inside the window before uploading, and keeps the preview of the uploaded image afterwards.

## 1.5.45 - 2026-10-17

- Media Studio now shows a coloured status banner for every upload step: no file chosen, file selected (name and size), uploaded and selected, or the exact failure reason.

## 1.5.44 - 2026-10-17

- Media Studio uploads now try the plugin endpoint first and fall back to the platform store endpoint, and any failure is shown in a clear red box with the HTTP status and server message so it can be diagnosed quickly.

## 1.5.43 - 2026-10-17

- Media Studio uploads for images now go through the plugin's own upload endpoint instead of the platform store response, so the uploaded image always appears at the top of the list and is selected, on every site build. Administrators can use it as well.

## 1.5.42 - 2026-10-17

- Media Studio now shows the installed LMS version next to its title, and the empty state explains that an uploaded file appears in the list immediately.

## 1.5.41 - 2026-10-17

- Draft courses now show a Publish button beside Save Changes, and new courses show Publish together with Save draft, so the first publish is one click. Published courses keep Save Changes only.

## 1.5.40 - 2026-10-17

- A new course always starts as Draft: the Publish Status selector only offers Draft until the course is created, and the primary action is labelled Publish (or Submit for approval for instructors without direct publishing).
- After the first publish the action becomes Save Changes and the status can be changed normally.

## 1.5.39 - 2026-10-17

- Uploading an image now keeps Media Studio open, shows the new image at the top of the list, and confirms it was selected, so the first upload is always visible.
- The builder action bar (Save Changes) stays visible while scrolling, under the site header, with a shadow when stuck.
- The course form stylesheet is cache-busted by file time so new styles apply immediately after an update.

## 1.5.38 - 2026-10-17

- A category typed in the form is preserved and stays selected when the course save is rejected by validation, so it is not lost while other required fields are completed.

## 1.5.37 - 2026-10-17

- Course images are uploaded only through Media Studio again; the extra device-upload row was removed from the course form.

## 1.5.36 - 2026-10-17

- When the platform Media Studio returns an empty list, the LMS now lists the files stored in storage/app/public/media itself, so existing images still appear in the picker; the empty state explains the two ways to add an image.

## 1.5.35 - 2026-10-17

- A category typed in the New category name field is now added to the dropdown and selected when the field loses focus or the form is submitted, so it is always saved with the course even when the Add new category button is not pressed. A saved category missing from the list is also shown.

## 1.5.34 - 2026-10-17

- Course images can now be uploaded straight from the course form (plugin-owned upload), independent of the platform Media Studio, so an image is always saved and previewed.
- The course and lesson description editors are taller and can be resized by dragging, and their text can be selected and copied normally (autoresize removed).

## 1.5.33 - 2026-10-17

- Media Studio now tolerates different media API response shapes (items/data/files, media/data/file) and derives a media URL from the stored path when the API does not return one, so freshly uploaded files appear and get selected instead of being ignored.
- Upload and selection problems now show a clear red message under the upload form instead of failing silently.

## 1.5.32 - 2026-10-17

- The Add new category button now sits under the category field: type a name, press the button and the category is added to the dropdown straight away (and selected), ready to be saved with the course.

## 1.5.31 - 2026-10-17

- Course images and media are now served through the LMS plugin (/lms/media-file/...) instead of the public /storage path, so they keep displaying when the storage symlink is missing or the hosting blocks it.
- Upload failures now show a clear message about the server upload limits instead of a silent failure.

## 1.5.30 - 2026-10-17

- Restore a visible Add new category button next to the Category dropdown: it reveals the New category name field, the typed name is saved as the course category, and the category then appears in the dropdown.

## 1.5.29 - 2026-10-17

- Fix a JavaScript error in the course builder that was raised after removing the base price field, which blocked the media picker from opening.

## 1.5.28 - 2026-10-17

- The separate base price and discount fields are gone: each training type now carries its own price, and the course price is taken from the selected type (the lowest one when the course runs both ways).
- Media Studio images can be deleted from the picker, and the picked course image can be removed from the course form.

## 1.5.27 - 2026-10-17

- Category is back to a full dropdown so every category stays visible, with an optional New category field underneath: leave it empty to use the selected category, or type a name to create a brand new one. No JavaScript toggle or hidden field is involved.

## 1.5.26 - 2026-10-17

- Category is now a single combobox field: pick a category from the suggestions or type a brand new one directly. The typed category is submitted with the course, saved, and added to the suggestions list, with no extra button or hidden field involved.

## 1.5.25 - 2026-10-17

- Students choose their training type (Online or In-person) while enrolling with a course code; the choice is stored on the enrollment and shown in My Learning.
- Enrolled students can switch their training type later from the course page when the course offers both modes.

## 1.5.24 - 2026-10-17

- Show the training prices on the public course page: when a course is offered in person (or both ways) the price card lists the online and in-person prices, injected through the details template slot so custom templates pick it up automatically.

## 1.5.23 - 2026-10-17

- Add a Training Type to courses: Online, In-person, or Both, with a dedicated price for each type. The instructor and admin course forms show only the price fields that apply, and the course page lists both prices when both modes are offered.
- Preselect the signed-in instructor in the Instructor field when an instructor creates a course.

## 1.5.22 - 2026-10-17

- Instructors (and admins) can add a brand new course category straight from the course form; the typed category is saved with the course and reused in the category list.
- The course title field now becomes Diploma Title when the program type is Diploma.
- Seats Available gains an Unlimited seats option that stores the unlimited enrollment limit instead of a fixed number.
- Fix the broken image icon in the Course Image uploader: the empty preview image is no longer rendered, and a preview is created only when a real image is selected.

## 1.5.21 - 2026-10-15

- Remove the redundant Add Lesson button from the course curriculum header; lessons are added from inside a unit.

## 1.5.20 - 2026-10-15

- Adding a curriculum unit or lesson no longer jumps the page to the top: the required course title is revealed with a smooth scroll and a highlight instead of an abrupt focus jump.

## 1.5.19 - 2026-10-15

- The course and lesson builder forms now use the active theme accent instead of their own blue palette; brand, hover, focus and soft-tint colours follow the administration theme with the previous values kept as fallbacks.

## 1.5.18 - 2026-10-15

- The Trash group in the account Teaching sub-actions is now collapsed by default and reveals Deleted courses and Deleted lessons only when it is clicked.

## 1.5.17 - 2026-10-15

- Remove the Courses and Lessons entries from the account Teaching sub-actions; keep Add new course, Add new lesson and the trash links.

## 1.5.16 - 2026-10-15

- The teaching sub-actions in the account sidebar now stay hidden until the Teaching tab is active.

## 1.5.15 - 2026-10-15

- Instructors can move their own courses and lessons to a trash, restore them, or delete them permanently from the teaching workspace.
- The account Teaching section now shows quick sub-actions: Courses, Add new course, Lessons, Add new lesson, and a Trash group with Deleted courses and Deleted lessons.

## 1.5.14 - 2026-10-15

- LMS frontend pages (portal, course details, course player, instructor profile) now take their colours from the active platform theme instead of a fixed brand palette.
- LMS administration screens follow the administration theme accent, text and muted colours.
- Previous colour values are kept as fallbacks so installations without the theme tokens keep their current look.

## 1.5.11 - 2026-09-19

- Use the same course, diploma, curriculum, lesson, media, and settings editors for administrators and instructors.
- Restrict the instructor course curriculum to lessons that belong to that exact course and reject cross-course ordering payloads server-side.
- Keep instructor course and lesson publishing subject to the existing per-instructor review policy.
- Scope instructor course, lesson, author, module, prerequisite, update, and deletion operations to content owned by that instructor.

## 1.5.10 - 2026-09-19

- Make instructor-ownership reconciliation safe during a hot plugin upgrade when PHP still has service classes from the previous release loaded.
- Keep the upgrade reconciliation self-contained in the lifecycle without depending on newly introduced runtime service methods.

## 1.5.9 - 2026-09-19

- Keep account tabs interactive for instructor-only users without changing Core authorization or granting administrative access.
- Assign courses and diplomas to active instructor records by `instructor_id` instead of a display-name string.
- Synchronize the new instructor's owner enrollment when a published program is reassigned and remove only the previous ownership enrollment.
- Preserve a previous instructor's independent free-course enrollment when ownership changes.
- Reconcile current and stale instructor-owner access automatically during plugin activation or update, and whenever an administrator saves the assignment.

## 1.5.8 - 2026-09-19

- Read the Course Player brand logo and site name from the platform's canonical `general.site_logo` and `general.site_title` settings.
- Remove the obsolete hard-coded settings image path and show the configured site name when no logo is set.

## 1.5.7 - 2026-09-19

- Replace interactive PeerTube iframes in lesson playback with the LMS-owned control surface.
- Control PeerTube playback, seeking, volume, speed, captions, duration, and progress through its embed messaging API.
- Disable all pointer and keyboard interaction with the PeerTube iframe so videos cannot navigate students away from the LMS player.
- Hide PeerTube titles, warnings, and instance links through supported embed parameters while retaining native controls for promotional-video modals.

## 1.5.6 - 2026-09-19

- Add native support for public PeerTube watch, short, and embed URLs in lesson videos and course promotional videos.
- Normalize PeerTube links to the official embed endpoint while remaining compatible with any PeerTube instance.
- Keep PeerTube's native playback controls interactive inside the LMS player.
- Centralize YouTube, Vimeo, and PeerTube embed URL resolution inside the LMS plugin.

## 1.5.5 - 2026-09-09

- Restyle Catalog course cards to match the compact Art INPA reference layout.
- Use a large contained course image followed by category, title, instructor, lessons, level, price, and a lightweight course link.
- Remove the card description, avatar, heavy action button, and elevated hover treatment.

## 1.5.4 - 2026-09-09

- Rebuild the complete public Courses experience inside the selected Catalog template.
- Add server-rendered search, category, level, sorting, empty-state, and pagination template data.
- Refresh the protected default Catalog template with the new responsive course discovery design.

## 1.5.3 - 2026-09-09

- Render the public Courses page through the selected active Catalog template with a protected system fallback.
- Allow active Catalog templates to be selected as the site-wide Courses page template.
- Remove the fixed catalog presentation assets while retaining the catalog data endpoint.

## 1.5.2 - 2026-09-08

- Point the account learning action to the public course catalog and label it Browse Courses.
- Improve the instructor request review layout across desktop and mobile screens.
- Hide empty instructor image previews and add spacing between the student email field and main-account action.

## 1.5.1 - 2026-09-04

- Follow the platform language direction across LMS public, student, and instructor interfaces.
- Preserve all existing learning content when the platform language changes.
- Require platform Core 3.4.4 or newer.

## 1.5.0 - 2026-09-03

- Enforce lesson-level immediate, scheduled, and prerequisite access rules in the course player.
- Store the prerequisite lesson explicitly and restrict it to the same course or diploma.
- Add automatic access for course instructors and automatic enrollment in published free learning programs.
- Add idempotent in-platform announcements for free programs and newly published lessons.
- Add a persistent LMS email outbox with a strict maximum of five messages per rolling ten-minute window.
- Normalize required LMS defaults and set existing diploma prices to 1,200 JOD with a 1,000 JOD sale price during upgrade.

## 1.4.2 - 2026-09-03

- Add an explicit per-instructor publishing policy managed from the LMS administration screens.
- Default new and approved instructors to administrator review for both courses and lessons.
- Enforce the instructor publishing policy in the server-side save path so legacy role permissions cannot bypass review.

## 1.4.1 - 2026-09-03

- Correct the shared Media Studio state styling so it stays closed initially, opens only from its explicit trigger, and closes reliably without covering instructor user selection forms.

## 1.4.0 - 2026-09-03

- Make legacy LMS migrations safely re-runnable when an existing installation has module tables but an incomplete Laravel migration ledger.
- Add an LMS-owned frontend teaching dashboard for creating and editing courses, diplomas, and lessons.
- Require administrator review for instructor submissions unless the instructor has the explicit publish permission.
- Notify LMS administrators when an instructor request, course, diploma, or lesson is submitted, and notify instructors of review decisions.
- Add an instructor application and approval workflow without creating duplicate platform users.
- Change Add Instructor to select an existing registered platform user instead of creating a new account.
- Add functional course-player notifications and a persistent light/dark mode toggle with clear moon and sun icons.
- Remove the unused instructor temporary-password route while retaining the active student invitation workflow.

## 1.3.4 - 2026-09-03

- Restore visible numbered navigation on the admin students list using the platform pagination contract.
- Preserve student filters and page-size parameters while navigating or jumping directly to a page number.

## 1.3.3 - 2026-08-31

- Publish the current stable learning-platform implementation as a synchronized standalone plugin release.
- Refresh course-detail and course-player asset cache keys for the packaged release.
- Retain the verified course login continuation, student profile isolation, course builder, lesson player, and progress workflows.

## 1.3.2 - 2026-08-30

- Preserve the intended course checkout/login continuation flow inside the LMS boundary.
- Package the course-player, demo lesson, course-image, and student-account navigation refinements completed during final platform testing.
- Keep LMS profile and settings data module-owned while reading the central platform identity where required.

## 1.3.1

- Previous stable LMS release.
