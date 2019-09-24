<?php


namespace BitPaySDK\Util\RESTcli;


use BitPayKeyUtils\KeyHelper\PrivateKey;
use BitPaySDK\Env;
use BitPaySDK\Exceptions\BitPayException;
use Exception;
use GuzzleHttp\Client as GuzzleHttpClient;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RequestOptions as RequestOptions;

class RESTcli
{
    /**
     * @var GuzzleHttpClient
     */
    protected $_client;
    /**
     * @var string
     */
    protected $_baseUrl;
    /**
     * @var PrivateKey
     */
    protected $_ecKey;
    /**
     * @var string
     */
    protected $_identity;

    public function __construct(string $environment, PrivateKey $ecKey)
    {
        $this->_ecKey = $ecKey;
        $this->_baseUrl = $environment == Env::Test ? Env::TestUrl : Env::ProdUrl;
        $this->init();
    }

    public function init()
    {
        try {
            $this->_identity = $this->_ecKey->getPublicKey()->__toString();
            $this->_client = new GuzzleHttpClient(
                [
                    'base_url' => $this->_baseUrl,
                    'defaults' => [
                        'headers' => [
                            "x-accept-version"     => Env::BitpayApiVersion,
                            'x-bitpay-plugin-info' => Env::BitpayPluginInfo,
                        ],
                    ],
                ]);
        } catch (Exception $e) {
            throw new BitPayException("RESTcli init failed : ".$e->getMessage());
        }
    }

    public function post($uri, array $formData = [], $signatureRequired = true): string
    {
        try {
            $fullURL = $this->_baseUrl.$uri;
            $headers = [
                'Content-Type' => 'application/json',
            ];

            if ($signatureRequired) {
                $headers['x-signature'] = $this->_ecKey->sign($fullURL.json_encode($formData));
                $headers['x-identity'] = $this->_identity;
            }

            /**
             * @var GuzzleHttp\Psr7\Response
             */
            $response = $this->_client->requestAsync(
                'POST', $fullURL, [
                $options[RequestOptions::SYNCHRONOUS] = false,
                'headers'            => $headers,
                RequestOptions::JSON => $formData,
            ])->wait();

            $responseJson = $this->responseToJsonString($response);

            return $responseJson;
        } catch (Exception $e) {
            throw new BitPayException("POST failed : ".$e->getMessage());
        }
    }

    public function get($uri, array $parameters = null, $signatureRequired = true): string
    {
        try {
            $fullURL = $this->_baseUrl.$uri;
            $headers = [
                'Content-Type' => 'application/json',
            ];

            if ($parameters) {
                $fullURL .= '?'.http_build_query($parameters);
            }

            if ($signatureRequired) {
                $headers['x-signature'] = $this->_ecKey->sign($fullURL);
                $headers['x-identity'] = $this->_identity;
            }

            /**
             * @var Response
             */
            $response = $this->_client->requestAsync(
                'GET', $fullURL, [
                $options[RequestOptions::SYNCHRONOUS] = false,
                'headers' => $headers,
                'query'   => $parameters,
            ])->wait();

            $responseJson = $this->responseToJsonString($response);

            return $responseJson;
        } catch (Exception $e) {
            throw new BitPayException("GET failed : ".$e->getMessage());
        }
    }

    public function delete($uri, array $parameters = null): string
    {
        try {
            $fullURL = $this->_baseUrl.$uri;
            if ($parameters) {
                $fullURL .= '?'.http_build_query($parameters);
            }

            $headers = [
                'Content-Type' => 'application/json',
                'x-signature'  => $this->_ecKey->sign($fullURL),
                'x-identity'   => $this->_identity,
            ];

            /**
             * @var Response
             */
            $response = $this->_client->requestAsync(
                'DELETE', $fullURL, [
                $options[RequestOptions::SYNCHRONOUS] = false,
                'headers' => $headers,
                'query'   => $parameters,
            ])->wait();

            $responseJson = $this->responseToJsonString($response);

            return $responseJson;
        } catch (Exception $e) {
            throw new BitPayException("DELETE failed : ".$e->getMessage());
        }
    }

    public function update($uri, array $formData = []): string
    {
        try {
            $fullURL = $this->_baseUrl.$uri;
            $headers = [
                'Content-Type' => 'application/json',
                'x-signature'  => $this->_ecKey->sign($fullURL.json_encode($formData)),
                'x-identity'   => $this->_identity,
            ];

            /**
             * @var Response
             */
            $response = $this->_client->requestAsync(
                'PUT', $fullURL, [
                $options[RequestOptions::SYNCHRONOUS] = false,
                'headers'            => $headers,
                RequestOptions::JSON => $formData,
            ])->wait();

            $responseJson = $this->responseToJsonString($response);

            return $responseJson;
        } catch (Exception $e) {
            throw new BitPayException("UPDATE failed : ".$e->getMessage());
        }
    }

    public function responseToJsonString(Response $response): string
    {
        if ($response == null) {
            throw new Exception("Error: HTTP response is null");
        }

        try {

            $body = json_decode($response->getBody()->getContents(), true);
            $error_message = false;
            $error_message = (!empty($body['error'])) ? $body['error'] : $error_message;
            $error_message = (!empty($body['errors'])) ? $body['errors'] : $error_message;
            $error_message = (is_array($error_message)) ? implode("\n", $error_message) : $error_message;
            if (false !== $error_message) {
                throw new BitpayException($error_message);
            }
            $jsonString = json_encode($body['data']);

            return $jsonString;

        } catch (Exception $e) {
            throw new BitPayException("failed to retrieve HTTP response body : ".$e->getMessage());
        }
    }

}