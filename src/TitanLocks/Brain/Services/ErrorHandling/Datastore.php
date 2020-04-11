<?php

namespace TitanLocks\Brain\Services\ErrorHandling;

use Google\Cloud\Datastore\DatastoreClient;

/**
 * Class Datastore
 * @package TitanLocks\Brain\Services\ErrorHandling
 *
 * @param $obj - object of datastore or storage after touched the API
 * @param $action - name of the class and function used when getting the error
 * @param $type - if the error was from 'datastore'
 *
 * @returns == false if datastore has no errors
 * https://cloud.google.com/datastore/docs/concepts/errors
 */
class Datastore
{

    // checks datastore result object for error field and if so then send error message to user, if not then datastore was a success
    public static function ErrorHandling($obj, $action, $type) {

        // checks for datastore error
        if (is_array($obj) && isset($obj['error'])) {

            // sets default datastore error message
            $error = 'Connection issue, please refresh and try again';

            // boolean to see if datastore log needs to be made for error
            $datastoreLog = false;

            if (!isset($obj['error']['status'])) {
                $datastoreLog = true;
            } else {
                switch ($obj['error']['status']) {
                    // Indicates that the request conflicted with another request.
                    case 'ABORTED':
                        $datastoreLog = true;
                        break;
                    // Indicates that the request attempted to insert an entity that already exists.
                    case 'ALREADY_EXISTS':
                        $datastoreLog = true;
                        break;
                    // A deadline was exceeded on the server.
                    case 'DEADLINE_EXCEEDED':
                        $datastoreLog = true;
                        break;
                    // Indicates that a precondition for the request was not met. The message field in the error response provides information about the precondition that failed. One possible cause is running a query that requires an index not yet defined.
                    case 'FAILED_PRECONDITION':
                        $datastoreLog = true;
                        break;
                    // Server returned an error.
                    case 'INTERNAL':
                        break;
                    // Indicates that a request parameter has an invalid value. The message field in the error response provides information as to which value was invalid.
                    case 'INVALID_ARGUMENT':
                        $datastoreLog = true;
                        break;
                    // Indicates that the request attempted to update an entity that does not exist.
                    case 'NOT_FOUND':
                        $datastoreLog = true;
                        break;
                    // Indicates that the user was not authorized to make the request.
                    case 'PERMISSION_DENIED':
                        $datastoreLog = true;
                        break;
                    // Indicates that the user has exceeded the project quota.
                    case 'RESOURCE_EXHAUSTED':
                        $datastoreLog = true;
                        break;
                    // Indicates that the request did not have valid authentication credentials.
                    case 'UNAUTHENTICATED':
                        $datastoreLog = true;
                        break;
                    // Server returned an error.
                    case 'UNAVAILABLE':
                        break;
                }
            }

            // upload the error to Our error log datastore so we can monitor where the errors are happening at
            if ($datastoreLog == true) {
                // datastore connection
                $datastore = new DatastoreClient();

                // datastore error handling log info to enter info database
                $datastoreErrorHandlingLogInformation = [
                    '$type' => $type,
                    'action' => $action,
                    'code' => $obj['error']['code'],
                    'message' => $obj['error']['message'],
                    'status' => $obj['error']['status']
                ];

                // create entity for datastore
                $datastoreErrorHandlingLog = $datastore->entity($datastore->key('DatastoreLog', $datastoreErrorHandlingLogInformation));

                // insert entity to datastore
                $datastore->insert($datastoreErrorHandlingLog);
            }
            // error caught return error message
            return $error;
        }
        // no error caught, return false
        return false;
    }

}