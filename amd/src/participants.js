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

/**
 * Some UI stuff for participants page.
 * This is also used by the report/participants/index.php because it has the same functionality.
 *
 * @module     quiz_teacheroverview/participants
 * @package    quiz_teacheroverview
 * @copyright  2017 Damyon Wiese
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Devlion Moodle Development <service@devlion.co> 
 */
define(['jquery', 'core/str', 'core/modal_factory', 'core/modal_events', 'core/templates', 'core/notification', 'core/ajax'],
        function($, Str, ModalFactory, ModalEvents, Templates, Notification, Ajax) {

    var SELECTORS = {
        SENDMESSAGESBUTTON: "#sendmessage"
    };

    /**
     * Constructor
     *
     * @param {Object} options Object containing options. Contextid is required.
     * Each call to templates.render gets it's own instance of this class.
     */
    var Participants = function(options) {

        this.courseId = options.courseid;
        this.noteStateNames = options.noteStateNames;
        this.stateHelpIcon = options.stateHelpIcon;

        this.attachEventListeners();
    };
    // Class variables and functions.

    /**
     * @var {Modal} modal
     * @private
     */
    Participants.prototype.modal = null;

    /**
     * @var {int} courseId
     * @private
     */
    Participants.prototype.courseId = -1;

    /**
     * @var {Object} noteStateNames
     * @private
     */
    Participants.prototype.noteStateNames = {};

    /**
     * @var {String} stateHelpIcon
     * @private
     */
    Participants.prototype.stateHelpIcon = "";

    /**
     * Private method
     *
     * @method attachEventListeners
     * @private
     */
    Participants.prototype.attachEventListeners = function() {

        $(SELECTORS.SENDMESSAGESBUTTON).on('click', function(e) {
            e.preventDefault();
            $('#participantsform #checkboxes').html('');
            // Find selected.
            var selected = [];
            $('input[name=\"attemptid[]\"]:checked').each(function() {
                selected.push($(this).data('userid'));
            });
            if (selected.length == 0) {
                return;
            }

            Participants.prototype.showSendMessage(selected).fail(Notification.exception);
        });
    };

    /**
     * Show the send message popup.
     *
     * @method showSendMessage
     * @private
     * @param {int[]} users
     * @return {Promise}
     */
    Participants.prototype.showSendMessage = function(users) {

        if (users.length == 0) {
            // Nothing to do.
            return $.Deferred().resolve().promise();
        }
        var titlePromise = null;
        if (users.length == 1) {
            titlePromise = Str.get_string('sendbulkmessagesingle', 'core_message');
        } else {
            titlePromise = Str.get_string('sendbulkmessage', 'core_message', users.length);
        }

        return $.when(
            ModalFactory.create({
                type: ModalFactory.types.SAVE_CANCEL,
                body: Templates.render('core_user/send_bulk_message', {})
            }),
            titlePromise
        ).then(function(modal, title) {
            // Keep a reference to the modal.
            this.modal = modal;

            this.modal.setTitle(title);
            this.modal.setSaveButtonText(title);

            // We want to focus on the action select when the dialog is closed.
            this.modal.getRoot().on(ModalEvents.hidden, function() {
                $(SELECTORS.BULKACTIONSELECT).focus();
                this.modal.getRoot().remove();
            }.bind(this));

            this.modal.getRoot().on(ModalEvents.save, this.submitSendMessage.bind(this, users));

            this.modal.show();

            return this.modal;
        }.bind(this));
    };

    /**
     * Send a message to these users.
     *
     * @method submitSendMessage
     * @private
     * @param {int[]} users
     * @param {Event} e Form submission event.
     * @return {Promise}
     */
    Participants.prototype.submitSendMessage = function(users) {

        var messageText = this.modal.getRoot().find('form textarea').val();

        var messages = [],
            i = 0;

        for (i = 0; i < users.length; i++) {
            messages.push({touserid: users[i], text: messageText});
        }

        return Ajax.call([{
            methodname: 'core_message_send_instant_messages',
            args: {messages: messages}
        }])[0].then(function(messageIds) {
            if (messageIds.length == 1) {
                return Str.get_string('sendbulkmessagesentsingle', 'core_message');
            } else {
                return Str.get_string('sendbulkmessagesent', 'core_message', messageIds.length);
            }
        }).then(function(msg) {
            Notification.addNotification({
                message: msg,
                type: "success"
            });
            return true;
        }).catch(Notification.exception);
    };

    return /** @alias module:core_user/participants */ {
        // Public variables and functions.

        /**
         * Initialise the unified user filter.
         *
         * @method init
         * @param {Object} options - List of options.
         * @return {Participants}
         */
        'init': function(options) {
            return new Participants(options);
        }
    };
});
