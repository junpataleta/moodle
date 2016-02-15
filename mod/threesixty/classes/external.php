<?php
/**
 * Created by PhpStorm.
 * User: jun
 * Date: 15/02/16
 * Time: 3:19 PM
 */
namespace mod_threesixty;

use external_api;
use external_description;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use external_warnings;
use stdClass;

/**
 * Class external.
 *
 * The external API for the 360-degree feedback module.
 *
 * @package mod_threesixty
 */
class external extends external_api {
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_questions_parameters() {
        return new external_function_parameters([]);
    }

    /**
     * The function itself
     * @return string welcome message
     */
    public static function get_questions() {
        $warnings = [];

        $questions = api::get_questions();

        return [
            'questions' => $questions,
            'warnings' => $warnings
        ];
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function get_questions_returns() {
        return new external_single_structure(
            [
                'questions' => new external_multiple_structure(
                    new external_single_structure(
                        [
                            'id' => new external_value(PARAM_INT, 'The question ID.'),
                            'question' => new external_value(PARAM_TEXT, 'The question text.'),
                            'type' => new external_value(PARAM_INT, 'The question type.'),
                            'typeName' => new external_value(PARAM_TEXT, 'The question type text value.')
                        ]
                    )
                ),
                'warnings' => new external_warnings()
            ]
        );
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function add_question_parameters() {
        return new external_function_parameters([
            'question' => new external_value(PARAM_TEXT, 'The question text.'),
            'type' => new external_value(PARAM_INT, 'The question type.')
        ]);
    }

    /**
     * The function itself
     * @return string welcome message
     */
    public static function add_question($question, $type) {
        $warnings = [];

        $params = external_api::validate_parameters(self::add_question_parameters(), ['question' => $question, 'type' => $type]);

        $dataobj = new stdClass();
        $dataobj->question = $params['question'];
        $dataobj->type = $params['type'];
        $questionid = api::add_question($dataobj);

        return [
            'questionid' => $questionid,
            'warnings' => $warnings
        ];
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function add_question_returns() {
        return new external_single_structure(
            [
                'questionid' => new external_value(PARAM_INT, 'The question ID of the added question.'),
                'warnings' => new external_warnings()
            ]
        );
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function update_question_parameters() {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'The question ID.'),
            'question' => new external_value(PARAM_TEXT, 'The question text.'),
            'type' => new external_value(PARAM_INT, 'The question type.')
        ]);
    }

    /**
     * The function itself
     * @return string welcome message
     */
    public static function update_question($id, $question, $type) {
        $warnings = [];

        $params = external_api::validate_parameters(self::update_question_parameters(), [
                'id' => $id,
                'question' => $question,
                'type' => $type
            ]
        );

        $dataobj = new stdClass();
        $dataobj->id = $params['id'];
        $dataobj->question = $params['question'];
        $dataobj->type = $params['type'];

        $result = api::update_question($dataobj);

        return [
            'result' => $result,
            'warnings' => $warnings
        ];
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function update_question_returns() {
        return new external_single_structure(
            [
                'result' => new external_value(PARAM_BOOL, 'The question update processing result.'),
                'warnings' => new external_warnings()
            ]
        );
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function delete_question_parameters() {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'The question ID.')
        ]);
    }

    /**
     * The function itself
     * @return string welcome message
     */
    public static function delete_question($id) {
        $warnings = [];

        $params = external_api::validate_parameters(self::delete_question_parameters(), [ 'id' => $id ]);

        $id = $params['id'];

        $result = api::delete_question($id);

        return [
            'result' => $result,
            'warnings' => $warnings
        ];
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function delete_question_returns() {
        return new external_single_structure(
            [
                'result' => new external_value(PARAM_BOOL, 'The question update processing result.'),
                'warnings' => new external_warnings()
            ]
        );
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_items_parameters() {
        return new external_function_parameters(
            [
                'threesixtyid' => new external_value(PARAM_INT, 'The 360-degree feedback ID.')
            ]
        );
    }

    /**
     * @param $threesixtyid
     * @return array
     * @throws \invalid_parameter_exception
     */
    public static function get_items($threesixtyid) {
        $warnings = [];
        $params = external_api::validate_parameters(self::get_items_parameters(), ['threesixtyid' => $threesixtyid]);

        $items = api::get_items($params['threesixtyid']);

        return [
            'items' => $items,
            'warnings' => $warnings
        ];
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function get_items_returns() {
        return new external_single_structure(
            [
                'items' => new external_multiple_structure(
                    new external_single_structure(
                        [
                            'id' => new external_value(PARAM_INT, 'The item ID.'),
                            'threesixtyid' => new external_value(PARAM_INT, 'The 360-degree feedback ID.'),
                            'questionid' => new external_value(PARAM_INT, 'The question ID.'),
                            'position' => new external_value(PARAM_INT, 'The item position'),
                            'question' => new external_value(PARAM_TEXT, 'The question text.'),
                            'type' => new external_value(PARAM_INT, 'The question type.')
                        ]
                    )
                ),
                'warnings' => new external_warnings()
            ]
        );
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function set_items_parameters() {
        return new external_function_parameters(
            [
                'threesixtyid' => new external_value(PARAM_INT, 'The 360-degree feedback ID.'),
                'questionids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'The question ID.')
                )
            ]
        );
    }

    /**
     * @param $threesixtyid
     * @return array
     * @throws \invalid_parameter_exception
     */
    public static function set_items($threesixtyid, $questionids) {
        $warnings = [];
        $params = external_api::validate_parameters(self::set_items_parameters(), [
            'threesixtyid' => $threesixtyid,
            'questionids' => $questionids
        ]);

        $result = api::set_items($params['threesixtyid'], $params['questionids']);

        return [
            'result' => $result,
            'warnings' => $warnings
        ];
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function set_items_returns() {
        return new external_single_structure(
            [
                'result' =>  new external_value(PARAM_BOOL, 'The processing result.'),
                'warnings' => new external_warnings()
            ]
        );
    }
}
