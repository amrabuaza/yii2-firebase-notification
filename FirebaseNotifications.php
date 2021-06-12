<?php
namespace opensooq\firebase;

use Exception;
use InvalidArgumentException;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\httpclient\Client;

/**
 * Class FirebaseNotifications
 *
 * @author Amr Alshroof
 * @package opensooq\firebase
 */
class FirebaseNotifications extends Component
{

    /** @var string the auth_key Firebase cloud messaging server key. */
    public $authKey;
    /** @var int timeout */
    public $timeout = 5;
    /** @var bool is host ssl verify */
    public $sslVerifyHost = false;
    /** @var bool ssl verify peer */
    public $sslVerifyPeer = false;
    /** @var string the api url for Firebase cloud messaging. */
    public $apiUrl = 'https://fcm.googleapis.com/fcm/send';

    /** @var Client http client */
    private $client;

    /**
     * @throws InvalidArgumentException
     */
    public function init()
    {
        if (empty($this->authKey)) {
            throw new InvalidArgumentException("Auth key can not be empty");
        }

        $this->client = new Client(['baseUrl' => $this->apiUrl]);
    }

    /**
     * Send notification
     *
     * @param $body
     * @return array
     * @throws InvalidConfigException
     * @throws \yii\httpclient\Exception
     */
    protected function send($body)
    {
        $response = $this->client->createRequest()
            ->setMethod('POST')
            ->setHeaders([
                "Authorization" => "key={$this->authKey}",
                "Content-Type"  => "application/json",
                'Expect' => '',
            ])
            ->setData(Json::encode($body))
            ->setOptions([
                CURLOPT_SSL_VERIFYHOST  => $this->sslVerifyHost,
                CURLOPT_SSL_VERIFYPEER  => $this->sslVerifyPeer,
                CURLOPT_TIMEOUT         => $this->timeout,
            ])
            ->send();

        // get response content
        $responseContent = $response->content;

        // check if response not success
        if (!$response->isOk) {
            // get clear response content as without html tags effect
            $responseContent = strip_tags($responseContent);

            return $this->prepareResponse($response->statusCode, $responseContent);
        }

        // check if response content contain error
        if (strpos($responseContent, 'Error')) {
            return $this->prepareResponse(400, $responseContent);
        }

        // return success response
        return $this->prepareResponse(200, 'Notification sent successfully', $responseContent);
    }

    /**
     * High level method to send notification for a specific tokens (registration_ids) with FCM
     *
     * @param array $tokens the registration ids
     * @param array $notification can be something like {title:, body:, sound:, badge:, click_action:, }
     * @param array $options other FCM options https://firebase.google.com/docs/cloud-messaging/http-server-ref#downstream-http-messages-json
     * @return array ['code' => 200 , 'message' => 'Notification sent successfully' , 'result' => ['multicast_id' => 'xxx' , 'success' => 1 ,  'failure' => 0]]
     * @throws Exception
     *
     * @example $notificationClient->sendNotification(['xxxx','xxxx'],['body' => 'test' , 'message' => 'test message'])
     *
     * @see https://firebase.google.com/docs/cloud-messaging/http-server-ref
     * @see https://firebase.google.com/docs/cloud-messaging/concept-options#notifications_and_data_messages
     */
    public function sendNotification($tokens, $notification, $options = [])
    {
        $body = [
            'registration_ids'  => $tokens,
            'notification'      => $notification,
        ];

        $body = ArrayHelper::merge($body, $options);

        return $this->send($body);
    }

    /**
     * Prepare response result based on code and message
     *
     * @param integer $code
     * @param string $message
     * @param null $result
     * @return array
     * @example $this->prepareResponse(500,"lose connection")
     * will return ['code' => 500 , 'message' => 'Internal Server Error , lose connection ' , 'result' => ]
     */
    protected function prepareResponse($code, $message, $result = null)
    {
        $codes = [
            200 => 'OK',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            500 => 'Internal Server Error',
            503 => 'Service Unavailable',
        ];

        $codeMessage    =  (isset($codes[$code])) ? $codes[$code] : '';
        $message        = "$codeMessage , $message";

        return [
            'code'      => $code,
            'message'   => $message,
            'result'    => $result
        ];
    }

}
