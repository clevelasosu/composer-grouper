<?php


namespace OSUCOE\Grouper;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;

class Grouper
{

    /**
     * @var Client
     */
    protected $client;
    public $timeout = 5;
    public $maxAttempts = 3;
    public $maxChangesPerQuery = 50;
    public $sleepBetweenChanges = 5;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * @param $url
     * @param $method
     * @param null $body
     * @return mixed
     * @throws GrouperException
     */
    protected function request($url, $method, $body=null) {

        $attempts = 0;
        do {
            try {

                $response = $this->client->request($method, $url, [
                    'body' => $body,
                    'timeout' => $this->timeout,
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                ]);
            } catch (ConnectException $e) {
                // Attempt to handle timeouts
                $attempts++;
                if ($attempts == $this->maxAttempts) {
                    throw new GrouperException("Connection Error to Grouper", 0, $e);
                } else {
                    // repeat the do loop
                    continue;
                }
            } catch (Exception $e) {
                throw new GrouperException("Unexpected Error to Grouper", 0, $e);
            }
            break;
        } while ($attempts < $this->maxAttempts);

        return json_decode($response->getBody());
    }

    /**
     * @param array $users
     * @param $grouperGroup
     * @param bool $replaceExisting
     * @return bool
     * @throws GrouperException
     */
    public function addUsersToGroup(array $users, $grouperGroup, $replaceExisting=false)
    {

        $replaceExisting = ($replaceExisting === true ? 'T' : 'F');
        $url = urlencode($grouperGroup) . '/members';

        $start = 0;
        $increment = $this->maxChangesPerQuery;
        $sleep = $this->sleepBetweenChanges;

        do {
            // Only do a replace the first time around
            if ($replaceExisting == "T" AND $start > 0) {
                $replaceExisting = "F";
            }

            // Get the next portion of users
            $usersThisRound = array_slice($users, $start, $increment);
//            echo "Doing Loop $start/$increment: ".count($usersThisRound).PHP_EOL;
            if (!count($usersThisRound)) {
                // We're done
                break;
            }

            $subjectLookups = [];
            foreach ($usersThisRound as $user) {
                $subjectLookups[] = ['subjectId' => $user];
            }

            $addMembers = [
                'WsRestAddMemberRequest' => [
                    'replaceAllExisting' => $replaceExisting,
                    'subjectLookups' => $subjectLookups,
                ],
            ];

            $addMembers = json_encode($addMembers, JSON_PRETTY_PRINT);

            // Make the real request
            $result = $this->request($url, 'POST', $addMembers);
            $resultMetadata = $result->WsAddMemberResults->resultMetadata;

            // If it's not successful, throw an error.  Otherwise the loop continues
            if ($resultMetadata->success != 'T') {
                throw new GrouperException($resultMetadata->resultCode);
            }

            // If we have fewer than a full $increment in the array, we should be done
            if (count($usersThisRound) < $increment) {
                break;
            }

            // Prepare for next loop
            $start += $increment;

            // If it's deemed beneficial to sleep between loops, do it here
            if ($sleep AND is_numeric($sleep)) {
                sleep($sleep);
            }

        } while (true);

        return true;
    }

    /**
     * @param array $users
     * @param $grouperGroup
     * @return bool
     * @throws GrouperException
     */
    public function removeUsersFromGroup(array $users, $grouperGroup)
    {
        $start = 0;
        $increment = $this->maxChangesPerQuery;
        $sleep = $this->sleepBetweenChanges;

        do {

            $usersThisRound = array_slice($users, $start, $increment);
//            echo "Doing Loop $start/$increment: ".count($usersThisRound).PHP_EOL;
            if (!count($usersThisRound)) {
                // We're done
                break;
            }

            $subjectLookups = [];
            foreach ($usersThisRound as $user) {
                $subjectLookups[] = ['subjectId' => $user];
            }

            $delMembers = [
                'WsRestDeleteMemberRequest' => [
                    'subjectLookups' => $subjectLookups,
                ],
            ];

            $delMembers = json_encode($delMembers, JSON_PRETTY_PRINT);

            $url = urlencode($grouperGroup) . '/members';
            $result = $this->request($url, 'PUT', $delMembers);

            $resultMetadata = $result->WsDeleteMemberResults->resultMetadata;
            if ($resultMetadata->success != 'T') {
                throw new GrouperException($resultMetadata->resultCode);
            }

            // If we have fewer than a full $increment in the array, we should be done
            if (count($usersThisRound) < $increment) {
                break;
            }

            // Prepare for next loop
            $start += $increment;

            // If it's deemed beneficial to sleep between loops, do it here
            if ($sleep AND is_numeric($sleep)) {
                sleep($sleep);
            }

        } while (true);

        return true;
    }

    /**
     * @param $grouperGroup
     * @return array
     * @throws GrouperException
     */
    public function getMembers($grouperGroup)
    {
        $url = urlencode($grouperGroup) . '/members';

        $body = $this->request($url, 'GET');

        // $body->WsGetMembersLiteResult->resultMetadata->success // T or F
        // http://software.internet2.edu/grouper/release/1.5.0/ws/api/edu/internet2/middleware/grouper/ws/soap/WsGetMembersLiteResult.WsGetMembersLiteResultCode.html?root=I2MI&view=co&pathrev=HEAD
        // $body->WsGetMembersLiteResult->resultMetadata->resultCode // SUCCESS
        // $body->WsGetMembersLiteResult->resultMetadata->resultMessage // string
        if ($body->WsGetMembersLiteResult->resultMetadata->resultCode != "SUCCESS") {
            throw new GrouperException($body->WsGetMembersLiteResult->resultMetadata->resultCode);
        }

        if (count($body->WsGetMembersLiteResult->wsSubjects) == 0) {
            return [];
        }

        $members = [];
        foreach ($body->WsGetMembersLiteResult->wsSubjects as $subject) {
            $members[] = $subject->id;
        }

        return $members;
    }
}