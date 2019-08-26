<?php

namespace App\Model\Service\Gitlab;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

abstract class GitlabServiceAbstract
{

    const BOOLEAN_TRUE = 'true';

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var string
     */
    protected $token;

    /**
     * @var int
     */
    protected $perPageDefault;

    public function __construct(ClientInterface $client, string $token, int $perPageDefault = 100)
    {
        $this->client = $client;
        $this->token = $token;
        $this->perPageDefault = $perPageDefault;
    }

    abstract protected function getListUrl(): string;

    abstract protected function getItemUrl(): string;

    /**
     * @param string[] $urlParameters Key-value
     * @param string[] $requestParameters Key-value
     * @return Collection
     */
    public function getList(array $urlParameters = [], array $requestParameters = []): Collection
    {
        $data = new Collection();

        $baseUrl = $this->getListUrl();
        foreach ($urlParameters as $key => $value) {
            $baseUrl = str_replace($key, $value, $baseUrl);
        }

        $page = $requestParameters['page'] ?? null;
        $currentPage = $page ?: 1;

        do {
            $requestParameters['page'] = $currentPage;
            $parts = $this->prepareRequestParameters($requestParameters);
            $url = $baseUrl . '?' . implode('&', $parts);

            try {
                $response = $this->client->get($url);
            }
            catch (ClientException $e) {
                // #12 Try to get Data from Group of project without group
                if ($e->getCode() == Response::HTTP_NOT_FOUND) {
                    return $data;
                }
                if ($e->getCode() == Response::HTTP_FORBIDDEN) {
                    echo "\n Error 403, url=".$url." \n";
                    return $data;
                }
                throw $e;
            }
            $content = $response->getBody()->getContents();

            $items = json_decode($content, true);
            $data = $data->merge($items);

            $currentPage++;
        }
        while (!empty($items) && !$page);

        return $data;
    }

    public function getItem(array $urlParameters = [], array $parameters = [])
    {
        // @todo
    }

    /**
     * @param array $requestParameters
     * @return array
     */
    protected function prepareRequestParameters(array $requestParameters): array
    {
        $requestParameters['private_token'] = $requestParameters['private_token'] ?? $this->token;
        $requestParameters['per_page'] = $requestParameters['per_page'] ?? $this->perPageDefault;
        $requestParameters['order_by'] = $requestParameters['order_by'] ?? 'updated_at';

        $parts = [];
        foreach ($requestParameters as $key => $value) {
            $parts[] = $key . '=' . $value;
        }

        return $parts;
    }

}