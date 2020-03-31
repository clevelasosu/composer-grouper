<?php


namespace OSUCOE\Grouper;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;

class Grouper
{

    /**
     * @var Client
     */
    protected $client;

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
        $maxAttempts = 3;
        $timeout = 5;

        do {
            try {

                $response = $this->client->request($method, $url, [
                    'body' => $body,
                    'timeout' => $timeout,
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                ]);
            } catch (ConnectException $e) {
                // Attempt to handle timeouts
                $attempts++;
                if ($attempts == $maxAttempts) {
                    throw new GrouperException("Connection Error to Grouper", 0, $e);
                } else {
                    // repeat the do loop
                    continue;
                }
            } catch (\Exception $e) {
                throw new GrouperException("Unexpected Error to Grouper", 0, $e);
            }
            break;
        } while ($attempts < $maxAttempts);

        return json_decode($response->getBody());
    }

    /**
     * @param array $users
     * @param $grouperGroup
     * @return bool
     * @throws GrouperException
     */
    public function addUsersToGroup(array $users, $grouperGroup)
    {

        $subjectLookups = [];
        foreach ($users as $user) {
            $subjectLookups[] = ['subjectId' => $user];
        }

        $addMembers = [
            'WsRestAddMemberRequest' => [
                'replaceAllExisting' => 'F',
                'subjectLookups' => $subjectLookups,
            ],
        ];

        $addMembers = json_encode($addMembers, JSON_PRETTY_PRINT);
        $url = urlencode($grouperGroup) . '/members';

        $result = $this->request($url, 'POST', $addMembers);
        $resultMetadata = $result->WsAddMemberResults->resultMetadata;

        if ($resultMetadata->success == 'T') {
            return true;
        } else {
            throw new GrouperException($resultMetadata->resultCode);
        }
    }

    /**
     * @param array $users
     * @param $grouperGroup
     * @return bool
     * @throws GrouperException
     */
    public function removeUsersFromGroup(array $users, $grouperGroup)
    {

        $subjectLookups = [];
        foreach ($users as $user) {
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
        if ($resultMetadata->success == 'T') {
            return true;
        } else {
            throw new GrouperException($resultMetadata->resultCode);
        }
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