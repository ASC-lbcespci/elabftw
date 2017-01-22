<?php
/**
 * \Elabftw\Elabftw\ExperimentsView
 *
 * @author Nicolas CARPi <nicolas.carpi@curie.fr>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */
namespace Elabftw\Elabftw;

use Exception;
use Datetime;

/**
 * Experiments View
 */
class ExperimentsView extends EntityView
{
    /** Read only switch */
    private $ro = false;

    /** show experiments from others in the team? */
    private $showTeam = false;

    /** instance of TeamGroups */
    public $TeamGroups;

    /** can be tag, query or filter */
    public $searchType = '';

    /** are we looking for exp related to an item ? */
    public $related = 0;

    /**
     * Need an instance of Experiments
     *
     * @param Entity $entity
     * @throws Exception
     */
    public function __construct(Entity $entity)
    {
        $this->Entity = $entity;
        $this->limit = $_SESSION['prefs']['limit'];
        $this->showTeam = $_SESSION['prefs']['show_team'];

        $this->TeamGroups = new TeamGroups($this->Entity->team);
    }

    /**
     * View experiment
     *
     * @return string HTML for viewXP
     */
    public function view()
    {
        $this->initViewEdit();
        $this->ro = $this->isReadOnly();

        if ($this->Entity->entityData['timestamped']) {
            $this->html .= $this->showTimestamp();
        }

        $this->html .= $this->buildView();
        $this->html .= $this->UploadsView->buildUploads('view');
        $this->html .= $this->buildComments();
        $this->html .= $this->buildCommentsCreate();
        $this->html .= $this->buildViewJs();

        return $this->html;
    }
    /**
     * Edit experiment
     *
     * @return string
     */
    public function edit()
    {
        $this->initViewEdit();
        // only owner can edit an experiment
        if (!$this->isOwner()) {
            throw new Exception(_('<strong>Cannot edit:</strong> this experiment is not yours!'));
        }

        // a locked experiment cannot be edited
        if ($this->Entity->entityData['locked']) {
            throw new Exception(_('<strong>This item is locked.</strong> You cannot edit it.'));
        }

        $this->html .= $this->buildEdit();
        $this->html .= $this->UploadsView->buildUploadForm();
        $this->html .= $this->UploadsView->buildUploads('edit');
        $this->html .= $this->buildEditJs();

        return $this->html;
    }

    /**
     * Generate HTML for show XP
     * we have html and html2 because to build html we need the idArr
     * from html2
     *
     * @return string
     */
    public function buildShow()
    {
        $html = '';
        $html2 = '';

        // RELATED SEARCH (links)
        if ($this->related) {

            $itemsArr = $this->Entity->readRelated($this->related);

        } else {

            if (!$this->showTeam) {
                // filter by user
                $this->Entity->useridFilter = " AND experiments.userid = " . $this->Entity->userid;
            }
            $itemsArr = $this->Entity->readAll();

        }

        // loop the results array and display results
        $idArr = array();
        foreach ($itemsArr as $item) {

            // fill an array with the ID of each item to use in the csv/zip export menu
            //$this->Entity->setId($item['id'], true);
            //if ($this->Entity->canRead) {
             //   $idArr[] = $this->Entity->id;
                $html2 .= $this->showUnique($item);
            //}

        }

        // show number of results found
        $count = count($itemsArr);
        if ($count === 0 && $this->searchType != '') {
            return display_message('ko_nocross', _("Sorry. I couldn't find anything :("));
        } elseif ($count === 0 && $this->searchType === '') {
            return display_message('ok_nocross', sprintf(_("Welcome to eLabFTW. %sClick here%s to create your first experiment."), "<a href='app/controllers/ExperimentsController.php?create=true'>", "</a>"));
        } else {
            $html .= $this->buildExportMenu($idArr, 'experiments');

            $total_time = get_total_time();
            $html .= "<p class='smallgray'>" . $count . " " .
                ngettext("result found", "results found", $count) . " (" .
                $total_time['time'] . " " . $total_time['unit'] . ")</p>";
        }
        $load_more_button = "<div class='center'>
            <button class='button' id='loadButton'>" . sprintf(_('Show %s more'), $this->limit) . "</button>
            <button class='button button-neutral' id='loadAllButton'>". _('Show all') . "</button>
            </div>";
        // show load more button if there are more results than the default display number
        if ($count > $this->limit) {
            $html2 .= $load_more_button;
        }
        $html .= $this->buildShowJs('experiments');
        return $html . $html2;
    }

    /**
     * Show an experiment
     *
     * @param array $item a unique experiment data
     * @return string
     */
    public function showUnique($item)
    {
        // dim the experiment a bit if it's not yours
        $opacity = '1';
        if ($this->Entity->userid != $item['userid']) {
            $opacity = '0.7';
        }
        $html = "<section class='item " . $this->display . "' style='opacity:" . $opacity . "; border-left: 6px solid #" . $item['color'] . "'>";
        $html .= "<a href='experiments.php?mode=view&id=" . $item['id'] . "'>";

        // show attached if there is a file attached
        if (isset($item['attachment'])) {
            $html .= "<img style='clear:both' class='align_right' src='app/img/attached.png' alt='file attached' />";
        }
        // we show the abstract of the experiment on mouse hover with the title attribute
        if ($this->Entity->canRead) {
            $bodyAbstract = str_replace("'", "", substr(strip_tags($item['body']), 0, 100));
        } else {
            $bodyAbstract = '';
        }
        $html .= "<a title='" . $bodyAbstract . "' href='experiments.php?mode=view&id=" . $item['id'] . "'>";
        $html .= "<p class='title'>";
        if ($item['timestamped']) {
            $html .= "<img class='align_right' src='app/img/stamp.png' alt='stamp' title='experiment timestamped' />";
        }
        // LOCK
        if ($item['locked']) {
            $html .= "<img style='padding-bottom:3px;' src='app/img/lock-blue.png' alt='lock' />";
        }
        // TITLE
        $html .= $item['title'] . "</p></a>";
        // STATUS
        $html .= "<span style='text-transform:uppercase;font-size:80%;padding-left:20px;color:#" . $item['color'] . "'>" . $item['name'] . " </span>";
        // DATE
        $html .= "<span class='date'><img class='image' src='app/img/calendar.png' /> " . Tools::formatDate($item['date']) . "</span> ";
        // TAGS
        $html .= $this->showTags('view');

        $html .= "</section>";

        return $html;
    }

    /**
     * Generate HTML for edit experiment
     *
     * @return string $html
     */
    private function buildEdit()
    {
        $html = '';
        // load tinymce
        $html .= "<script src='app/js/edit.mode.min.js'></script>";

        $html .= $this->backToLink('experiments');

        $html .= "<section class='box' id='main_section' style='border-left: 6px solid #" . $this->Entity->entityData['color'] . "'>";
        $html .= "<img class='align_right' src='app/img/big-trash.png' title='delete' alt='delete' onClick=\"experimentsDestroy(" . $this->Entity->id . ", '" . _('Delete this?') . "')\" />";

        // tags
        $html .= $this->showTags('edit');

        // main form
        $html .= "<form method='post' action='app/controllers/ExperimentsController.php' enctype='multipart/form-data'>";
        $html .= "<input name='update' type='hidden' value='true' />";
        $html .= "<input name='id' type='hidden' value='" . $this->Entity->id . "' />";

        // DATE
        $html .= "<div class='row'><div class='col-md-4'>";
        $html .= "<img src='app/img/calendar.png' title='date' alt='calendar' />";
        $html .= "<label for='datepicker'>" . _('Date') . "</label>";
        // if firefox has support for it: type = date
        // https://bugzilla.mozilla.org/show_bug.cgi?id=825294
        $html .= " <input name='date' id='datepicker' size='8' type='text' value='" . $this->Entity->entityData['date'] . "' />";
        $html .= "</div>";

        // VISIBILITY
        $html .= "<div class='col-md-4'>";
        $html .= "<img src='app/img/eye.png' alt='visibility' />";
        $html .= "<label for='visibility_select'>" . _('Visibility') . "</label>";
        $html .= " <select id='visibility_select' onchange='updateVisibility(" . $this->Entity->id . ", this.value)'>";
        $html .= "<option value='organization' ";
        if ($this->Entity->entityData['visibility'] === 'organization') {
            $html .= "selected";
        }
        $html .= ">" . _('Everyone with an account') . "</option>";
        $html .= "<option value='team' ";
        if ($this->Entity->entityData['visibility'] === 'team') {
            $html .= "selected";
        }
        $html .= ">" . _('Only the team') . "</option>";
        $html .= "<option value='user' ";
        if ($this->Entity->entityData['visibility'] === 'user') {
            $html .= "selected";
        }
        $html .= ">" . _('Only me') . "</option>";

        // Teamgroups
        $teamGroupsArr = $this->TeamGroups->readAll();
        foreach ($teamGroupsArr as $teamGroup) {
            $html .= "<option value='" . $teamGroup['id'] . "' ";
            if ($this->Entity->entityData['visibility'] === $teamGroup['id']) {
                $html .= "selected";
            }
            $html .= ">Only " . $teamGroup['name'] . "</option>";
        }
        $html .= "</select></div>";

        // STATUS
        $html .= "<div class='col-md-4'>";
        $html .= "<img src='app/img/status.png' alt='status' />";
        $html .= "<label for='status_select'>" . ngettext('Status', 'Status', 1) . "</label>";
        $html .= " <select id='status_select' name='status' onchange='updateStatus(" . $this->Entity->id . ", this.value)'>";

        $Status = new Status($this->Entity->team);
        $statusArr = $Status->readAll();

        foreach ($statusArr as $status) {
            $html .= "<option ";
            if ($this->Entity->entityData['status'] === $status['id']) {
                $html .= "selected ";
            }
            $html .= "value='" . $status['id'] . "'>" . $status['name'] . "</option>";
        }
        $html .= "</select></div></div>";

        // TITLE
        $html .= "<h4>" . _('Title') . "</h4>";
        $html .= "<input id='title_input' name='title' rows='1' value='" . $this->Entity->entityData['title'] . "' required />";

        // BODY
        $html .= "<h4>" . ngettext('Experiment', 'Experiments', 1) . "</h4>";
        $html .= "<textarea id='body_area' class='mceditable' name='body' rows='15' cols='80'>";
        $html .= $this->Entity->entityData['body'] . "</textarea>";

        $html .= "<div id='saveButton'>
            <button type='submit' name='Submit' class='button'>" ._('Save and go back') . "</button>
            </div></form>";

        // REVISIONS
        $Revisions = new Revisions($this->Entity);
        $html .= $Revisions->showCount();

        // LINKS
        $html .= "<section>
                <img src='app/img/link.png' alt='link' /> <h4 style='display:inline'>" . _('Linked items') . "</h4><br>";
        $html .= "<span id='links_div'>";
        $html .= $this->showLinks($this->Entity->id, 'edit');
        $html .= "</span>";
        $html .= "<p class='inline'>" . _('Add a link') . "</p> ";
        $html .= "<input id='linkinput' size='60' type='text' name='link' placeholder='" . _('from the database') . "' />";
        $html .= "</section>";

        // end main section
        $html .= "</section>";

        $html .= $this->injectChemEditor();

        return $html;
    }

    /**
     * Check we own the experiment
     *
     * @return bool
     */
    private function isOwner()
    {
        return $this->Entity->entityData['userid'] == $_SESSION['userid'];
    }


    /**
     * If int, get the name of the team group instead of a number
     *
     * @return string
     */
    private function getVisibility()
    {
        if (Tools::checkId($this->Entity->entityData['visibility'])) {
            return $this->TeamGroups->readName($this->Entity->entityData['visibility']);
        }
        return ucfirst($this->Entity->entityData['visibility']);
    }

    /**
     * Check if the experiment is read only
     *
     * @return bool
     */
    private function isReadOnly()
    {
        return $this->Entity->canRead && !$this->Entity->canWrite;
    }

    /**
     * Show info on timestamp
     *
     * @return string
     */
    private function showTimestamp()
    {
        $Users = new Users();
        $timestamper = $Users->read($this->Entity->entityData['timestampedby']);

        // we clone the object so we don't mess with the type
        $ClonedUploads = clone $this->UploadsView->Uploads;

        $ClonedUploads->type = 'exp-pdf-timestamp';
        $pdf = $ClonedUploads->readAll();

        $ClonedUploads->type = 'timestamp-token';
        $token = $ClonedUploads->readAll();

        $date = new DateTime($this->Entity->entityData['timestampedwhen']);

        return display_message(
            'ok_nocross',
            _('Experiment was timestamped by') . " " . $timestamper['firstname'] . " " . $timestamper['lastname'] . " " . _('on') . " " . $date->format('Y-m-d') . " " . _('at') . " " . $date->format('H:i:s') . " "
            . $date->getTimezone()->getName() . " <a href='uploads/" . $pdf[0]['long_name'] . "'><img src='app/img/pdf.png' title='" . _('Download timestamped pdf') . "' alt='pdf' /></a> <a href='uploads/" . $token[0]['long_name'] . "'><img src='app/img/download.png' title=\"" . _('Download token') . "\" alt='download token' /></a> <a href='#'><img onClick=\"decodeAsn1('" . $token[0]['long_name'] . "', '" . $this->Entity->entityData['id'] . "')\" src='app/img/info.png' title=\"" . _('Decode token') . "\" alt='decode token' /></a><div style='color:black;overflow:auto;display:hidden' id='decodedDiv'></div>"
        );
    }

    /**
     * Output HTML for viewing an experiment
     *
     */
    private function buildView()
    {
        $html = '';

        $html .= $this->backToLink('experiments');

        if ($this->ro) {
            $Users = new Users();
            $userArr = $Users->read($this->Entity->entityData['userid']);
            $ownerName = $userArr['firstname'] . ' ' . $userArr['lastname'];
            $message = sprintf(_('Read-only mode. Experiment of %s.'), $ownerName);
            $html .= display_message('ok', $message);
        }

        $html .= "<section class='item' style='padding:15px;border-left: 6px solid #" . $this->Entity->entityData['color'] . "'>";
        $html .= "<span class='top_right_status'><img src='app/img/status.png'>" . $this->Entity->entityData['name'] .
            "<img src='app/img/eye.png' alt='eye' />" . $this->getVisibility() . "</span>";
        $html .= "<div><img src='app/img/calendar.png' title='date' alt='Date :' /> " .
            Tools::formatDate($this->Entity->entityData['date']) . "</div>
        <a class='elab-tooltip' href='experiments.php?mode=edit&id=" . $this->Entity->entityData['id'] . "'><span>Edit</span><img src='app/img/pen-blue.png' alt='Edit' /></a>
    <a class='elab-tooltip' href='app/controllers/ExperimentsController.php?duplicateId=" . $this->Entity->entityData['id'] . "'><span>Duplicate Experiment</span><img src='app/img/duplicate.png' alt='Duplicate' /></a>
    <a class='elab-tooltip' href='make.php?what=pdf&id=" . $this->Entity->entityData['id'] . "&type=experiments'><span>Make a PDF</span><img src='app/img/pdf.png' alt='PDF' /></a>
    <a class='elab-tooltip' href='make.php?what=zip&id=" . $this->Entity->entityData['id'] . "&type=experiments'><span>Make a ZIP</span><img src='app/img/zip.png' alt='ZIP' /></a> ";

        // lock
        $onClick = " onClick=\"toggleLock('experiments', " . $this->Entity->entityData['id'] . ")\"";
        $imgSrc = 'unlock.png';
        $alt = _('Lock/Unlock item');
        if ($this->Entity->entityData['locked'] != 0) {
            $imgSrc = 'lock-gray.png';
            // don't allow clicking lock if experiment is timestamped
            if ($this->Entity->entityData['timestamped']) {
                $onClick = '';
            }
        }
        $html .= "<a class='elab-tooltip' href='#'><span>" . $alt . "</span><img id='lock'" . $onClick . " src='app/img/" . $imgSrc . "' alt='" . $alt . "' /></a> ";
        // show timestamp button if not timestamped already
        if (!$this->Entity->entityData['timestamped']) {
            $html .= "<a class='elab-tooltip'><span>Timestamp Experiment</span><img onClick='confirmTimestamp()' src='app/img/stamp.png' alt='Timestamp' /></a>";
            $html .= "<div id='confirm-timestamp' title='" . _('Timestamp this experiment?') . "'>";
            $html .= "<p><span class='ui-icon ui-icon-alert' style='float:left; margin:12px 12px 20px 0;'></span>";
            $html .= _('Once timestamped an experiment cannot be edited anymore! Are you sure you want to do this?');
            $html .= "</p></div>";
        }

        $html .= $this->showTags('view');
        // TITLE : click on it to go to edit mode only if we are not in read only mode
        $html .= "<div ";
        if (!$this->ro && !$this->Entity->entityData['locked']) {
            $html .= "OnClick=\"document.location='experiments.php?mode=edit&id=" . $this->Entity->entityData['id'] . "'\"";
        }
        $html .= " class='title_view'>";
        $html .= $this->Entity->entityData['title'] . "</div>";
        // BODY (show only if not empty, click on it to edit
        if ($this->Entity->entityData['body'] != '') {
            $html .= "<div id='body_view' ";
            // make the body clickable only if we are not in read only
            if (!$this->ro && !$this->Entity->entityData['locked']) {
                $html .= "OnClick=\"document.location='experiments.php?mode=edit&id=" . $this->Entity->entityData['id'] . "'\"";
            }
            $html .= " class='txt'>" . $this->Entity->entityData['body'] . "</div>";
            $html .= "<br>";
        }

        $html .= $this->showLinks($this->Entity->id, 'view');

        // DISPLAY eLabID
        $html .= "<p class='elabid'>" . _('Unique eLabID:') . " " . $this->Entity->entityData['elabid'];
        $html .= "</section>";

        return $html;
    }

    /**
     * Build the JS code for edit mode
     *
     * @return string
     */
    private function buildEditJs()
    {
        $Tags = new Tags($this->Entity);

        $html = "<script>
        // READY ? GO !!
        $(document).ready(function() {
            // AUTOSAVE
            var typingTimer;                // timer identifier
            var doneTypingInterval = 7000;  // time in ms between end of typing and save

            // user finished typing, save work
            function doneTyping () {
                quickSave('experiments', " . $this->Entity->id . ");
            }
            // KEYBOARD SHORTCUTS
            key('" . $_SESSION['prefs']['shortcuts']['create'] . "', function(){location.href = 'app/controllers/ExperimentsController?create=true'});
            key('" . $_SESSION['prefs']['shortcuts']['submit'] . "', function(){document.forms['editXP'].submit()});

            // autocomplete the tags
            $('#createTagInput').autocomplete({
                source: [" . $Tags->generateTagList('autocomplete') . "]
            });

            // autocomplete the links
            $( '#linkinput' ).autocomplete({
                source: [" . getDbList('default') . "]
            });

            // CREATE TAG
            // listen keypress, add tag when it's enter
            $('#createTagInput').keypress(function (e) {
                createTag(e, 'experiments', " . $this->Entity->id . ");
            });
            // CREATE LINK
            // listen keypress, add link when it's enter
            $('#linkinput').keypress(function (e) {
                experimentsCreateLink(e, " . $this->Entity->id . ");
            });

            // DATEPICKER
            $( '#datepicker' ).datepicker({dateFormat: 'yymmdd'});
            // If the title is 'Untitled', clear it on focus
            $('#title_input').focus(function(){
                if ($(this).val() === 'Untitled') {
                    $('#title_input').val('');
                }
            });

            // EDITOR
            tinymce.init({
                mode : 'specific_textareas',
                editor_selector : 'mceditable',
                content_css : 'app/css/tinymce.css',
                plugins : 'table textcolor searchreplace code fullscreen insertdatetime paste charmap save image link pagebreak mention',
                pagebreak_separator: '<pagebreak>',
                toolbar1: 'undo redo | bold italic underline | fontsizeselect | alignleft aligncenter alignright alignjustify | superscript subscript | bullist numlist outdent indent | forecolor backcolor | charmap | image link | save',
                removed_menuitems : 'newdocument',
                // save button :
                save_onsavecallback: function() {
                    quickSave('experiments', " . $this->Entity->id . ");
                },
                // keyboard shortcut to insert today's date at cursor in editor
                setup : function(editor) {
                    editor.addShortcut('ctrl+shift+d', 'add date at cursor', function() { addDateOnCursor(); });
                    editor.on('keydown', function(event) {
                        clearTimeout(typingTimer);
                    });
                    editor.on('keyup', function(event) {
                        clearTimeout(typingTimer);
                        typingTimer = setTimeout(doneTyping, doneTypingInterval);
                    });
                },
                mentions: {
                    source: [" . getDbList('mention') . "],
                    delimiter: '#'
                },
                language : '" . $_SESSION['prefs']['lang'] . "',
                style_formats_merge: true,
                style_formats: [
                    {
                        title: 'Image Left',
                        selector: 'img',
                        styles: {
                            'float': 'left',
                            'margin': '0 10px 0 10px'
                        }
                     },
                     {
                         title: 'Image Right',
                         selector: 'img',
                         styles: {
                             'float': 'right',
                             'margin': '0 0 10px 10px'
                         }
                     }
                ]
        });";

        $html .= $this->injectCloseWarning();
        $html .= "});</script>";

        return $html;
    }

    /**
     * Output html for displaying links
     *
     * @param int $id Experiment id
     * @param string $mode edit or view
     * @return string $html
     */
    public function showLinks($id, $mode)
    {
        $linksArr = $this->Entity->Links->read();
        $html = '';

        // Check there is at least one link to display
        if (count($linksArr) > 0) {
            $html .= "<ul class='list-group'>";
            foreach ($linksArr as $link) {
                if ($mode === 'edit') {
                    $html .= "<li class='list-group-item'>" . $link['name'] . " - <a href='database.php?mode=view&id=" . $link['itemid'] . "'>" .
                        $link['title'] . "</a>";
                    $html .= "<a onClick=\"experimentsDestroyLink(" . $link['linkid'] . ", " . $id . ", '" . _('Delete this?') . "')\">
                    <img class='align_right' src='app/img/small-trash.png' title='delete' alt='delete' /></a></li>";
                } else {
                    $html .= "<li class='list-group-item'><img src='app/img/link.png'> " . $link['name'] . " - <a href='database.php?mode=view&id=" . $link['itemid'] . "'>" .
                    $link['title'] . "</a></li>";
                }
            }
            $html .= "</ul>";
        }
        return $html;
    }

    /**
     * Build JS for view mode
     *
     * @return string
     */
    private function buildViewJs()
    {
        $html = "<script>
            function commentsUpdate() {
                // Experiment comment is editable
                $('div#expcomment').on('mouseover', '.editable', function(){
                    $('div#expcomment p.editable').editable('app/controllers/CommentsController.php', {
                        name: 'commentsUpdate',
                        tooltip : 'Click to edit',
                        indicator : '" ._('Saving') . "',
                        submit : '" . _('Save') . "',
                        cancel : '" . _('Cancel') . "',
                        style : 'display:inline',
                        callback : function() {
                            // now we reload the comments part to show the comment we just submitted
                            $('#expcomment_container').load('experiments.php?mode=view&id=" .
                            $this->Entity->id . " #expcomment');
                            // we reload the function so editable zones are editable again
                            commentsUpdate();
                        }
                    })
                });
            }
            // TIMESTAMP
            function confirmTimestamp() {
                $('#confirm-timestamp').dialog({
                    resizable: false,
                    height: 'auto',
                    width: 400,
                    modal: true,
                    buttons: {
                        'Timestamp it': function() {
                            timestamp(" . $this->Entity->id . ");
                        },
                        Cancel: function() {
                            $(this).dialog('close');
                        }
                    }
                });
            }

            // READY ? GO !!
            $(document).ready(function() {
                $('#confirm-timestamp').hide();
                $('#commentsCreateButtonDiv').hide();

                // Keyboard shortcuts
                key('" . $_SESSION['prefs']['shortcuts']['create'] .
                    "', function(){location.href = 'app/controllers/ExperimentsController?create=true'});
                key('" . $_SESSION['prefs']['shortcuts']['edit'] .
                    "', function(){location.href = 'experiments.php?mode=edit&id=" . $this->Entity->id . "'});
                // make editable
                setInterval(commentsUpdate, 50);
            });
            </script>";
        return $html;
    }

    /**
     * Display comments for an experiment
     *
     */
    private function buildComments()
    {
        $Comments = new Comments($this->Entity);
        $commentsArr = $Comments->read();

        //  we need to add a container here so the reload function in the callback of .editable() doesn't mess things up
        $html = "<section id='expcomment_container'>";
        $html .= "<div id='expcomment' class='box'>";
        $html .= "<h3><img src='app/img/comment.png' alt='comment' />" . _('Comments') . "</h3>";

        if (is_array($commentsArr)) {
            // there is comments to display
            foreach ($commentsArr as $comment) {
                if (empty($comment['firstname'])) {
                    $comment['firstname'] = '[deleted]';
                }
                $html .= "<div class='expcomment_box'>
                    <img class='align_right' src='app/img/small-trash.png' ";
                $html .= "title='delete' alt='delete' onClick=\"commentsDestroy(" .
                    $comment['id'] . ", " . $this->Entity->id . ", '" . _('Delete this?') . "')\" />";
                $html .= "<span>On " . $comment['datetime'] . " " . $comment['firstname'] . " " .
                    $comment['lastname'] . " wrote :</span><br />";
                $html .= "<p class='editable' id='" . $comment['id'] . "'>" . $comment['comment'] . "</p></div>";
            }
        }
        return $html;
    }

    /**
     * HTML for the add new comment block
     */
    private function buildCommentsCreate()
    {
        $html = "<textarea onFocus='commentsCreateButtonDivShow()' id='commentsCreateArea' placeholder='" .
            _('Add a comment') . "'></textarea>";
        $html .= "<div id='commentsCreateButtonDiv' class='submitButtonDiv'>";
        $html .= "<button class='button' id='commentsCreateButton' onClick='commentsCreate(" .
            $this->Entity->id . ")'>" . _('Save') . "</button></div></div></section>";

        return $html;
    }
}
