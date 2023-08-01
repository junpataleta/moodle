import ajax from "core/ajax";

/**
 * Given a course ID, we want to fetch the groups, so we may fetch their users.
 *
 * @method groupFetch
 * @param {int} courseid ID of the course to fetch the users of.
 * @return {object} jQuery promise
 */
export const groupFetch = (courseid) => {
    const request = {
        methodname: 'core_group_get_groups_for_selector',
        args: {
            courseid: courseid,
        },
    };
    return ajax.call([request])[0];
};
