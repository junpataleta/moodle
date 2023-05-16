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
 * Allow the user to search for learners within the singleview report.
 *
 * @module    gradereport_singleview/user
 * @copyright 2023 Mathew May <mathew.solutions>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import UserSearch from 'core/comboboxsearch/searchtype/user';
import Url from 'core/url';
import {renderForPromise, replaceNodeContents} from 'core/templates';

// Define our standard lookups.
const selectors = {
    component: '.user-search',
    courseid: '[data-region="courseid"]',
};
const component = document.querySelector(selectors.component);
const courseID = component.querySelector(selectors.courseid).dataset.courseid;

export default class User extends UserSearch {

    constructor() {
        super();
    }

    static init() {
        return new User();
    }

    /**
     * Build the content then replace the node.
     */
    async renderDropdown() {
        const {html, js} = await renderForPromise('gradereport_grader/search/resultset', {
            users: this.getMatchedResults().slice(0, 5),
            hasresults: this.getMatchedResults().length > 0,
            searchterm: this.getSearchTerm(),
        });
        replaceNodeContents(this.getHTMLElements().searchDropdown, html, js);
    }

    /**
     * Stub out default required function unused here.
     * @returns {null}
     */
    selectAllResultsLink() {
        return null;
    }

    /**
     * Build up the view all link that is dedicated to a particular result.
     *
     * @param {Number} userID The ID of the user selected.
     * @returns {string|*}
     */
    selectOneLink(userID) {
        return Url.relativeUrl('/grade/report/singleview/index.php', {
            id: courseID,
            searchvalue: this.getSearchTerm(),
            item: 'user',
            userid: userID,
        }, false);
    }
}
