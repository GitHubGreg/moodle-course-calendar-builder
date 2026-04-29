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

$string['pluginname'] = 'Course calendar';
$string['coursecalendar'] = 'Course calendar';
$string['managecoursecalendar'] = 'Course calendar: Setup';
$string['viewcoursecalendar'] = 'Course calendar';
$string['managepageheading'] = 'Course calendar: Setup';
$string['studentpageheading'] = 'Course calendar';
$string['studentnoactivecalendar'] = 'There is no active semester calendar for this course yet.';

$string['showtourbtn'] = 'Show walkthrough';
$string['showtourbtn_help'] = 'Re-start the guided walkthrough for this page. A short series of tooltips will highlight the main sections and explain what each one does.';

$string['intro_manage'] = 'Set up this course calendar by following the next recommended step. Blueprints hold reusable topics, course links choose which blueprint this Moodle course uses, and semester calendars are the student-facing schedules built from that blueprint.';
$string['intro_calendar'] = 'This is the week-by-week grid students will see. The top row (header) shows the days and Lecture/Lab mode for each column. Use the action buttons above to auto-fill the grid from your blueprint, add academic timeline rules (semester start/end, holidays), or open the student preview.';
$string['intro_rules'] = 'Rules describe your academic calendar: when the semester starts and ends, which days there is no class, day swaps (e.g. a Friday that follows a Monday schedule), and any other notes. Add rules below, then click <strong>Apply Rules to Calendar</strong> to generate the week labels and closed-day markers on your grid.';
$string['intro_coverage'] = 'This page compares your blueprint topics to what is actually placed on the grid. Use it to spot topics you forgot to schedule (<em>Missing</em>) and grid cells you still need to fill (<em>Empty slots</em>).';
$string['intro_importtopics'] = 'Already have a course schedule in HTML? Paste it here and the importer will create blueprint topics for you in the right order. You can also bulk-update eLesson URLs and, if you need to start over, delete all topics in this blueprint.';
$string['intro_aiimport'] = 'Paste or upload your college academic calendar so Moodle can extract the semester dates, holidays, and key events into a JSON list you can review and edit. Click <strong>Apply semester dates as rules</strong> to convert each event into a timeline rule on this calendar.';

$string['section_linkcourse'] = 'Course blueprint';
$string['section_linkcourse_help'] = 'A blueprint is your reusable master list of topics for a class (for example, "Mechanics Blueprint"). Connecting this Moodle course to a blueprint is what lets you build a semester calendar from it.';
$string['section_calendars'] = 'Semester calendars';
$string['section_calendars_help'] = 'A semester calendar is the week-by-week grid students actually see. Each semester you teach this course you will create a new calendar (e.g. "Fall 2026") from the linked blueprint, then open the builder to arrange topics into weeks.';
$string['section_blueprintlibrary'] = 'Blueprint library';
$string['section_blueprintlibrary_help'] = 'Blueprints are your reusable master lists of topics for the classes you teach. Create one blueprint per class (e.g. "Mechanics Blueprint", "Biology 200 Blueprint"). Each blueprint holds its own topics, and you reuse the same blueprint every semester you teach that class. Only you can edit the blueprints you own.';
$string['section_topics'] = 'Blueprint topics';
$string['section_topics_help'] = 'Topics are the individual items (lectures, labs, eLessons, tests, homework) that make up a class. Add them once to your blueprint and they will be available to drop onto every semester calendar built from this blueprint. Drag to reorder; the order here controls the default order used by Auto-populate.';

$string['manuallinkheading_help'] = 'Pick the blueprint that matches this course and click Set blueprint. You can change this link later without losing data.';
$string['choseblueprintautohint'] = 'Choose which blueprint should be used for this course. We\'ve tried to auto-detect and select the right option below, but you can change it.';

$string['section_builderactions'] = 'Links to related tools';
$string['section_builderactions_help'] = 'Quick links to other pages for this calendar: manage blueprint content, edit timeline rules, open the student preview in a new tab, copy an iframe embed code, or run the Coverage Check to see what is placed vs missing.';
$string['section_automation'] = 'Auto-fill helpers';
$string['section_automation_help'] = 'Shortcut buttons that place (or remove) content on the grid in bulk. Auto-populate drops all blueprint topics into empty cells in the right order. Fill Problem Sessions fills empty Lab-mode cells with a "Problem Session" label. The red buttons delete content so you can start over - they only affect this one calendar.';
$string['section_buildertoolbar'] = 'Save / Undo / Redo';
$string['section_buildertoolbar_help'] = 'Changes you make to cells are held in your browser until you click Save All (or press Ctrl+S). Undo/Redo work across the edits made in this session. An "Unsaved changes" badge appears whenever you have pending changes.';
$string['section_introtexts'] = 'Intro texts (optional)';
$string['section_introtexts_help'] = 'Optional student-facing text shown above the semester grid. Use the left and right areas for course reminders, overview text, useful links, office hours, or other context students should see before the calendar.';
$string['section_buildergrid'] = 'The semester grid';
$string['section_buildergrid_help'] = 'The top row is the header: columns 1-3 are class days (pick a weekday and Lecture/Lab mode for each), column 4 is a homework column. Each row below is one week of the semester. Drag any content cell onto another cell to swap them. Click "Edit cell" to change a cell\'s content, pick a topic, or toggle highlighting.';

$string['section_rulesapply'] = 'Apply rules';
$string['section_rulesapply_help'] = 'Once you have added rules (at minimum, SEMESTER_START and SEMESTER_END), click Apply Rules to Calendar. The grid will regenerate week labels, mark no-class days, and annotate day swaps. Any manual edits you made to cells are preserved - only the auto-generated content is refreshed.';
$string['section_rulesexisting'] = 'Existing rules';
$string['section_rulesexisting_help'] = 'All rules currently defined for this calendar. Toggle a rule off to exclude it from the next Apply without deleting it. Delete removes it permanently. You can have many NO_CLASS and OTHER rules, but only one active SEMESTER_START and one active SEMESTER_END.';
$string['section_rulescreate'] = 'Create a rule';
$string['section_rulescreate_help'] = 'Pick a rule type, a date, and an optional label/description. Rule types: Semester start/end anchor the first and last week; No class marks a holiday or closure; Day swap handles days that follow another weekday\'s schedule (e.g. "Monday schedule on Tuesday"); Other adds a free-form note on a specific date.';

$string['ruletype_SEMESTER_START'] = 'Semester start date';
$string['ruletype_SEMESTER_END'] = 'Semester end date';
$string['ruletype_NO_CLASS'] = 'No class / college closed';
$string['ruletype_DAY_SWAP'] = 'Day swap (follow a different day\'s schedule)';
$string['ruletype_OTHER'] = 'Other note';

$string['coveragefoundheading_help'] = 'Topics from your blueprint that are currently placed on the grid. Green means they are showing up somewhere in the semester.';
$string['coveragemissingheading_help'] = 'Active topics in your blueprint that have not been placed anywhere on the grid yet. Use Auto-populate or drop them into cells manually.';
$string['coverageemptyheading_help'] = 'Grid cells below the header that have no content. Good if you are waiting to fill them; a problem if you thought the grid was complete.';

$string['importdangerzone'] = 'Danger zone';
$string['importdangerzone_help'] = 'Irreversible operations on this blueprint\'s topics. Use with caution - there is no undo. Topics referenced by existing semester calendars cannot be deleted unless you remove them from the calendar first.';
$string['importtopicsfromhtml_help'] = 'Paste an HTML table (e.g. copied from a syllabus or an existing course site). The importer detects topic types (LECTURE, LAB, eLESSON, TEST, HOMEWORK) from common content patterns and creates one blueprint topic per detected row. Pick the column layout that matches your source - most courses are "Lecture/Lecture/Lab" or "Lecture/Lecture/Lecture".';
$string['importelessonlinkslabel_help'] = 'If you already have eLesson topics in this blueprint and you want to bulk-update their URLs, paste HTML containing the new eLesson links. The matcher compares link text to the first bullet of each eLesson topic\'s content and updates the href where it finds a match.';

$string['aiimportuploadlabel_help'] = 'Provide your college academic calendar - key dates, semester boundaries, holidays - either by pasting free-form text on the first tab, or by uploading a PDF on the second tab. The extractor reads the input directly and returns a structured list of semester dates; the more context you provide, the better the extraction.';
$string['aiimportresultlabel_help'] = 'These semester dates were extracted as JSON. Review them before applying - you can edit the JSON directly to fix mistakes. Each entry becomes one rule when you click Apply semester dates as rules.';

$string['courselinkcurrent_manual'] = 'Linked to <strong>{$a}</strong>.';
$string['courselinkcurrent_auto'] = 'Linked to <strong>{$a->name}</strong> (auto-detected, {$a->confidence}% confident).';
$string['autosuggestionfoundplain'] = 'We think this course matches <strong>{$a->name}</strong> ({$a->confidence}% confident). Use it?';

$string['courselinkheading'] = 'Course to blueprint link';
$string['courselinkcurrent'] = 'Current link: {$a}';
$string['courselinkmode'] = '(mode: {$a})';
$string['courselinkconfidence'] = '(confidence: {$a}%)';
$string['courselinknone'] = 'No blueprint is currently linked to this course.';
$string['courselinkupdated'] = 'Course link updated.';
$string['courseautolinked'] = 'Course linked using auto-link suggestion.';
$string['courselinkremoved'] = 'Course link removed.';
$string['manuallinkheading'] = 'Choose blueprint';
$string['manuallinksubmit'] = 'Set blueprint';
$string['unlinksubmit'] = 'Remove course link';
$string['courseblueprintlinkedbadge'] = 'Linked';
$string['courseblueprintautobadge'] = 'Auto, {$a}% confidence';
$string['linknoteslabel'] = 'Link notes';
$string['blueprintlabel'] = 'Blueprint';
$string['autolinkapply'] = 'Apply auto-link suggestion';
$string['autolinknotes'] = 'Auto-linked from course metadata heuristic.';
$string['autosuggestionfound'] = 'Auto-link suggestion: {$a}';
$string['autosuggestionambiguous'] = 'Auto-link is ambiguous. Top candidates: {$a}';
$string['noautosuggestion'] = 'No confident auto-link suggestion is currently available.';

$string['setupstatus_heading'] = 'Setup status';
$string['setupstatus_blueprint_label'] = 'Blueprints';
$string['setupstatus_blueprint_none'] = 'None yet';
$string['setupstatus_blueprint_count'] = '{$a} available';
$string['setupstatus_link_label'] = 'Course link';
$string['setupstatus_link_none'] = 'Not linked';
$string['setupstatus_topic_label'] = 'Linked topics';
$string['setupstatus_topic_unavailable'] = 'Choose a blueprint first';
$string['setupstatus_topic_count'] = '{$a} active';
$string['setupstatus_calendar_label'] = 'Semester calendars';
$string['setupstatus_calendar_count'] = '{$a->total} total, {$a->active} active';
$string['setupnext_label'] = 'Recommended next step';
$string['setupnext_createblueprint_action'] = 'Create blueprint';
$string['setupnext_restoreblueprint_action'] = 'Review blueprint library';
$string['setupnext_linkcourse_action'] = 'Choose blueprint';
$string['setupnext_addtopics_action'] = 'Manage topics';
$string['setupnext_createcalendar_action'] = 'Create semester calendar';
$string['setupnext_opencalendar_action'] = 'Open calendar builder';
$string['setupnext_createblueprint_title'] = 'Create your first blueprint';
$string['setupnext_createblueprint_body'] = 'A blueprint is the reusable topic library for this course. Create it once, then reuse it every semester.';
$string['setupnext_restoreblueprint_title'] = 'Make a blueprint available';
$string['setupnext_restoreblueprint_body'] = 'You have blueprints, but none are active. Restore one or create a new active blueprint before linking this course.';
$string['setupnext_linkcourse_title'] = 'Choose the blueprint for this course';
$string['setupnext_linkcourse_body'] = 'Linking this Moodle course to a blueprint lets you build semester calendars from the right topic library.';
$string['setupnext_addtopics_title'] = 'Add topics to the linked blueprint';
$string['setupnext_addtopics_body'] = '{$a} is linked, but it has no active topics yet. Import topics or create a few before building a semester calendar.';
$string['setupnext_createcalendar_title'] = 'Create the first semester calendar';
$string['setupnext_createcalendar_body'] = 'The course is linked and topics are ready. Create a semester calendar, then open the builder to arrange the schedule.';
$string['setupnext_opencalendar_title'] = 'Open or maintain a semester calendar';
$string['setupnext_opencalendar_body'] = 'This course has a calendar. Open the builder to arrange topics, apply rules, check coverage, or preview the student view.';
$string['calendarrecommend_reason_active'] = 'Recommended because it is the active calendar for this course.';
$string['calendarrecommend_reason_coursematch'] = 'Recommended because its semester and year match this Moodle course.';
$string['calendarrecommend_reason_courseconfig'] = 'Recommended because it matches this Moodle course\'s configured start date.';
$string['calendarrecommend_reason_currentdate'] = 'Recommended because today falls within this calendar\'s semester dates.';
$string['calendarrecommend_reason_upcoming'] = 'Recommended because it is the nearest upcoming semester calendar.';
$string['calendarrecommend_reason_newest'] = 'Recommended because it is the most recent calendar available.';

$string['blueprintlibraryheading'] = 'Blueprint library';
$string['createblueprintheading'] = 'Create blueprint';
$string['blueprintnamelabel'] = 'Name';
$string['blueprintshortcodelabel'] = 'Shortcode';
$string['blueprintdescriptionlabel'] = 'Description';
$string['createblueprintsubmit'] = 'Create blueprint';
$string['createblueprintbutton'] = 'Create new blueprint';
$string['editblueprintbutton'] = 'Edit';
$string['saveblueprintsubmit'] = 'Save blueprint';
$string['archiveblueprintsubmit'] = 'Archive blueprint';
$string['unarchiveblueprintsubmit'] = 'Unarchive blueprint';
$string['blueprintstatusactive'] = 'Status: Active';
$string['blueprintstatusarchived'] = 'Status: Archived';
$string['blueprinttopiccount'] = '{$a} active topics';
$string['noblueprints'] = 'No blueprints yet. Create one to start linking courses.';
$string['blueprintcreated'] = 'Blueprint created.';
$string['blueprintupdated'] = 'Blueprint updated.';
$string['blueprintarchived'] = 'Blueprint archived.';
$string['blueprintunarchived'] = 'Blueprint restored.';
$string['errorblueprintnamerequired'] = 'Blueprint name is required.';
$string['errorblueprintduplicate'] = 'You already have a blueprint with this name.';
$string['errorarchivedblueprintlink'] = 'Archived blueprints cannot be linked. Unarchive it first.';
$string['invalidblueprintownership'] = 'You can only modify your own blueprints.';
$string['invalidtopictype'] = 'Invalid topic type.';

$string['topiclibraryheading'] = 'Blueprint topics';
$string['topicblueprintcontextlabel'] = 'Editing blueprint';
$string['topicfilterlabel'] = 'Filter by type';
$string['topicfilterall'] = 'All topic types';
$string['applyfilter'] = 'Apply';
$string['archivedshort'] = 'Archived';
$string['notopicswithoutblueprint'] = 'Select or create a blueprint to manage topics.';
$string['notopicsfound'] = 'No topics for this blueprint/filter yet.';
$string['createtopicheading'] = 'Create topic';
$string['createtopicbutton'] = 'Create new topic';
$string['edittopicbutton'] = 'Edit';
$string['topicdraghandle'] = 'Drag to reorder topic';
$string['topicsortablelistname'] = 'Topics (drag to reorder)';
$string['topicreordersaved'] = 'Topic order saved.';
$string['topictitlelabel'] = 'Topic title';
$string['topictypelabel'] = 'Topic type';
$string['topiccontentlabel'] = 'Content HTML';
$string['createtopicsubmit'] = 'Create topic';
$string['savetopicsubmit'] = 'Save topic';
$string['movetopicupsubmit'] = 'Move up';
$string['movetopicdownsubmit'] = 'Move down';
$string['toggletopicsubmit'] = 'Toggle active';
$string['deletetopicsubmit'] = 'Delete topic';
$string['topiccreated'] = 'Topic created.';
$string['topicupdated'] = 'Topic updated.';
$string['topicdeleted'] = 'Topic deleted.';
$string['topicreordered'] = 'Topic order updated.';
$string['topicactivated'] = 'Topic activated.';
$string['topicdeactivated'] = 'Topic deactivated.';
$string['errortopictitlerequired'] = 'Topic title is required.';
$string['errortopicinuse'] = 'Cannot delete topic. It is used in {$a->count} calendar(s): {$a->calendars}';
$string['topicstatusactive'] = 'Active';
$string['topicstatusinactive'] = 'Inactive';
$string['topicstatusline'] = 'Order {$a->order} - {$a->status}';

$string['invalidsemester'] = 'Invalid semester.';
$string['invalidcalendarcontext'] = 'Invalid calendar context for this course.';
$string['calendarsectionheading'] = 'Semester calendars';
$string['calendarneedslink'] = 'Link this course to a blueprint before creating semester calendars.';
$string['calendarneedstopics'] = 'Add active topics to the linked blueprint before creating a new semester calendar.';
$string['createcalendarheading'] = 'Create semester calendar';
$string['createcalendarbutton'] = 'Create new semester calendar';
$string['editcalendarbutton'] = 'Edit';
$string['calendaryearlabel'] = 'Year';
$string['calendarsemesterlabel'] = 'Semester';
$string['calendartitlelabel'] = 'Title';
$string['calendartitleplaceholder'] = 'Optional display title';
$string['createcalendarsubmit'] = 'Create semester calendar';
$string['savecalendarsubmit'] = 'Save calendar';
$string['togglecalendarsubmit'] = 'Toggle active';
$string['deletecalendarsubmit'] = 'Delete calendar';
$string['calendarcreated'] = 'Semester calendar created.';
$string['calendarupdated'] = 'Semester calendar updated.';
$string['calendardeleted'] = 'Semester calendar deleted.';
$string['calendaractivated'] = 'Semester calendar activated.';
$string['calendardeactivated'] = 'Semester calendar deactivated.';
$string['calendarstatusactive'] = 'Status: Active';
$string['calendarstatusinactive'] = 'Status: Inactive';
$string['calendarbadgeactive'] = 'Active';
$string['calendarbadgeinactive'] = 'Inactive';
$string['calendarrecommendedbadge'] = 'Recommended';
$string['nocalendars'] = 'No semester calendars yet for this course.';
$string['errorcalendarduplicate'] = 'A calendar for this course/year/semester already exists.';
$string['errorinvalidyear'] = 'Year must be between 2000 and 2200.';
$string['opencalendarbuildersubmit'] = 'Open builder';
$string['builderpageheading'] = 'Semester builder (basic)';
$string['backtomanage'] = 'Back to calendar manager';
$string['buildercontextlabel'] = 'Editing calendar: {$a}';
$string['addweekrowsubmit'] = 'Add week row';
$string['savecellsubmit'] = 'Save cell';
$string['weekrowadded'] = 'Week row added.';
$string['cellsaved'] = 'Cell saved.';
$string['errorinvalidcell'] = 'Invalid row/column selection.';
$string['removelastweekrowsubmit'] = 'Remove last week row';
$string['weekrowremoved'] = 'Last week row removed.';
$string['errornoweekrowstoremove'] = 'No week rows to remove.';
$string['saveheadersubmit'] = 'Save header';
$string['headercellsaved'] = 'Header cell saved.';
$string['errorinvalidheadercol'] = 'Invalid header column selection.';
$string['errorinvalidheaderconfig'] = 'Invalid header day or mode.';
$string['errorheaderreadonly'] = 'Use header controls to edit row 0 cells.';
$string['errorweeklabelreadonly'] = 'Week label cells are read-only.';
$string['errorinvalidblocktype'] = 'Invalid block type.';
$string['errorinvalidtopicselection'] = 'Select a valid topic for this blueprint.';
$string['blocktypetext'] = 'Text block';
$string['blocktypetopic'] = 'Topic block';
$string['selecttopicplaceholder'] = 'Select topic...';
$string['selectedtopiclabel'] = 'Selected topic: {$a}';
$string['topicinactive'] = 'inactive';
$string['cellheadingplaceholder'] = 'Optional cell heading / annotation';
$string['cellhighlightedlabel'] = 'Highlighted';
$string['cellverticalcentredlabel'] = 'Vertically centred';
$string['deletecellsubmit'] = 'Delete cell block';
$string['celldeleted'] = 'Cell block deleted.';
$string['cellcleared'] = 'Cell cleared.';
$string['cellalreadyempty'] = 'Cell is already empty.';
$string['celltopiccontenthandledseparately'] = 'This cell shows a shared blueprint topic, so its content is edited below instead of in the cell content field.';
$string['sharedtopiceditorheading'] = 'Shared blueprint topic';
$string['sharedtopiceditwarning'] = 'You are editing the original blueprint topic. These changes will appear anywhere this topic block is used in this calendar or another course calendar using the same blueprint.';
$string['savesharedtopicsubmit'] = 'Save shared topic';
$string['topicupdatedfrombuilder'] = 'Shared blueprint topic updated.';

$string['editcellsummary'] = 'Edit cell';
$string['contenthtmlplaceholder'] = 'Cell content (HTML)';
$string['managecontentlink'] = 'Manage Content';
$string['openpreviewlink'] = 'Open Semester Preview';
$string['copyiframesubmit'] = 'Copy Iframe Code';
$string['saveallsubmit'] = 'Save All';
$string['undobtn'] = 'Undo';
$string['redobtn'] = 'Redo';
$string['unsavedchangesbadge'] = 'Unsaved changes';
$string['previewpageheading'] = 'Semester Calendar Preview';
$string['previewempty'] = 'This calendar has no content yet.';

$string['manageruleslink'] = 'Manage Rules';
$string['rulespagetitle'] = 'Academic Timeline Rules';
$string['backtobuilder'] = 'Back to builder';
$string['existingrulesheading'] = 'Existing rules';
$string['norules'] = 'No rules defined yet. Add semester start/end dates to begin.';
$string['createruleheading'] = 'Create rule';
$string['createrulesubmit'] = 'Create rule';
$string['createrulebutton'] = 'Create new rule';
$string['editrulebutton'] = 'Edit';
$string['saverulesubmit'] = 'Save rule';
$string['opencalendarbuilderprominent'] = 'Open calendar builder';
$string['ruletypelabel'] = 'Rule type';
$string['ruledatelabel'] = 'Date';
$string['rulelabellabel'] = 'Label';
$string['rulestatuslabel'] = 'Status';
$string['ruleactionslabel'] = 'Actions';
$string['rulelabelplaceholder'] = 'e.g. College Closed, Thanksgiving';
$string['ruledescriptionlabel'] = 'Description';
$string['ruledescriptionplaceholder'] = 'Optional description';
$string['fromdaylabel'] = 'From day (DAY_SWAP only)';
$string['todaylabel'] = 'To day (DAY_SWAP only)';
$string['dayswapfieldshelp'] = 'The From day and To day fields are only used for DAY_SWAP rules.';
$string['togglerulesubmit'] = 'Toggle';
$string['deleterulesubmit'] = 'Delete';
$string['applyrulesbtn'] = 'Apply Rules to Calendar';
$string['rulecreated'] = 'Rule created.';
$string['ruleupdated'] = 'Rule updated.';
$string['ruledeleted'] = 'Rule deleted.';
$string['ruleactivated'] = 'Rule activated.';
$string['ruledeactivated'] = 'Rule deactivated.';
$string['ruleactive'] = 'Active';
$string['ruleinactive'] = 'Inactive';
$string['rulesapplied'] = 'Rules applied. {$a} week(s) generated.';
$string['errorinvaliddate'] = 'Invalid date.';
$string['invalidruletype'] = 'Invalid rule type.';
$string['errorrulesmissingstartend'] = 'Both SEMESTER_START and SEMESTER_END rules are required before applying.';
$string['errorrulesendbeforestart'] = 'SEMESTER_END must be after SEMESTER_START.';
$string['errorrulestartendexists'] = 'An active rule of this type already exists. Deactivate or delete the existing one first.';

// Topic placement automation and coverage check.
$string['autopopulatebtn'] = 'Auto-populate';
$string['autopopulateconfirm'] = 'This will place topics into empty grid cells based on blueprint order. Existing content will not be overwritten. Continue?';
$string['autopopulatedone'] = 'Auto-populate complete: {$a->lectures} lecture/eLesson/test, {$a->labs} lab, {$a->homework} homework placed.';
$string['fillproblemsessionsbtn'] = 'Fill Problem Sessions';
$string['fillproblemsessionsconfirm'] = 'This will insert "Problem Session" into all empty Lab-mode cells. Continue?';
$string['problemsessionsfilled'] = '{$a} Problem Session cell(s) filled.';
$string['coveragechecklink'] = 'Coverage Check';
$string['coveragepagetitle'] = 'Coverage Check';
$string['coveragefoundheading'] = 'Found topics';
$string['coveragemissingheading'] = 'Missing topics';
$string['coverageemptyheading'] = 'Empty slots';
$string['coveragenofound'] = 'No topics placed yet.';
$string['coveragenomissing'] = 'All active topics are placed.';
$string['coveragenoempty'] = 'No empty content slots.';
$string['deletenonheaderbtn'] = 'Delete Non-Header Blocks';
$string['deletenonheaderconfirm'] = 'This will delete ALL blocks below the header row, including week labels and content. This cannot be undone. Continue?';
$string['nonheaderdeleted'] = '{$a} block(s) deleted.';
$string['deletenonheadernontextbtn'] = 'Delete Topics & Problem Sessions';
$string['deletenonheadernontextconfirm'] = 'This will delete all TOPIC blocks and "Problem Session" TEXT blocks, preserving week labels and other text content. Continue?';
$string['nonheadernontextdeleted'] = '{$a} block(s) deleted.';

// Student view, intro texts, and embed.
$string['studentviewheading'] = 'Course Calendar';
$string['courseinfointroleftlabel'] = 'Intro text (left)';
$string['courseinfointrorightlabel'] = 'Intro text (right)';
$string['saveintrotextssubmit'] = 'Save intro texts';
$string['courseinfosaved'] = 'Course info saved.';
$string['embedpagetitle'] = 'Calendar';

// Migration helpers: HTML topic import and bulk operations.
$string['importtopicspagetitle'] = 'Import Topics';
$string['importtopicsfromhtml'] = 'Seed topics from pasted HTML';
$string['importtopicshtmllabel'] = 'Paste HTML table';
$string['importtopicslayoutlabel'] = 'Column layout';
$string['importtopicssubmit'] = 'Import topics';
$string['importtopicsdone'] = '{$a->created} topic(s) created, {$a->skipped} non-topic cell(s) skipped.';
$string['importelessonlinkslabel'] = 'Paste HTML with eLesson links';
$string['importelessonlinkssubmit'] = 'Update eLesson links';
$string['importelessonlinksdone'] = '{$a->updated} topic(s) updated, {$a->notfound} link(s) not matched.';
$string['deletealltopicsbtn'] = 'Delete All Topics';
$string['deletealltopicsconfirm'] = 'This will permanently delete ALL topics for this blueprint. Continue?';
$string['deletealltopicsdone'] = '{$a} topic(s) deleted.';
$string['deletealltopicsblocked'] = 'Cannot delete: some topics are referenced by calendar blocks. Remove them first or use force delete.';
$string['importtopicslink'] = 'Import Topics';

// AI-assisted semester date extraction.
$string['settinggeminikey'] = 'Gemini API Key';
$string['settinggeminikeyhelp'] = 'API key for Google Gemini. Required for the Semester Dates extraction feature.';
$string['aiimportpagetitle'] = 'Semester Dates';
$string['aiimportuploadlabel'] = 'Provide semester dates';
$string['aiimporttabtext'] = 'Paste dates';
$string['aiimporttabpdf'] = 'Upload PDF';
$string['aiimporttextplaceholder'] = 'Paste semester dates, academic calendar text, key dates, or holidays...';
$string['aiimportpdfhelp'] = 'Upload a PDF containing semester dates, such as the official college academic calendar. Keep files under 20 MB.';
$string['aiimportpdfsubmit'] = 'Extract semester dates from PDF';
$string['aiimportnotext'] = 'Please paste some text before submitting.';
$string['aiimportnopdf'] = 'Please choose a PDF file to upload.';
$string['aiimportpdfinvalid'] = 'The uploaded file does not appear to be a valid PDF.';
$string['aiimportpdftoolarge'] = 'PDF is too large. Please upload a file smaller than 20 MB.';
$string['aiimportsubmit'] = 'Extract semester dates';
$string['aiimportresultlabel'] = 'Semester dates (edit JSON if needed)';
$string['aiimportapplysubmit'] = 'Apply semester dates as rules';
$string['aiimportapplied'] = '{$a} rule(s) created from semester dates.';
$string['aiimporterror'] = 'Semester date extraction failed. Check your API key and try again.';
$string['aiimportnoapikey'] = 'Gemini API key is not configured. Go to Site administration > Plugins > Local plugins > Course Calendar to set it.';
$string['aiimportlink'] = 'Semester Dates';

$string['coursecalendar:managesettings'] = 'Manage course calendar plugin settings';
$string['coursecalendar:managecalendar'] = 'Manage course calendar';
$string['coursecalendar:viewcalendar'] = 'View course calendar';
$string['coursecalendar:manage'] = 'Manage course calendar (legacy)';
$string['coursecalendar:view'] = 'View course calendar (legacy)';

