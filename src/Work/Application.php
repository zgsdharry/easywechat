<?php

declare(strict_types=1);

namespace EasyWeChat\Work;

use EasyWeChat\Kernel\Client;
use EasyWeChat\Kernel\Traits\InteractWithClient;
use EasyWeChat\Kernel\Traits\InteractWithCache;
use EasyWeChat\Kernel\Traits\InteractWithConfig;
use EasyWeChat\Kernel\Traits\InteractWithHttpClient;
use EasyWeChat\Kernel\Traits\InteractWithServerRequest;
use EasyWeChat\Kernel\Contracts\AccessToken as AccessTokenInterface;
use EasyWeChat\Work\Contracts\Account as AccountInterface;
use EasyWeChat\Work\Contracts\Application as ApplicationInterface;
use EasyWeChat\Kernel\Contracts\Server as ServerInterface;
use Overtrue\Socialite\Contracts\ProviderInterface as SocialiteProviderInterface;
use Overtrue\Socialite\Providers\WeWork;

class Application implements ApplicationInterface
{
    use InteractWithConfig;
    use InteractWithCache;
    use InteractWithServerRequest;
    use InteractWithHttpClient;
    use InteractWithClient;

    protected ?Encryptor $encryptor = null;
    protected ?ServerInterface $server = null;
    protected ?AccountInterface $account = null;
    protected ?JsApiTicket $ticket = null;
    protected ?AccessTokenInterface $accessToken = null;

    public function getAccount(): AccountInterface
    {
        if (!$this->account) {
            $this->account = new Account(
                corpId: $this->config->get('corp_id'),
                secret: $this->config->get('secret'),
                token: $this->config->get('token'),
                aesKey: $this->config->get('aes_key'),
            );
        }

        return $this->account;
    }

    public function setAccount(AccountInterface $account): static
    {
        $this->account = $account;

        return $this;
    }

    public function getEncryptor(): Encryptor
    {
        if (!$this->encryptor) {
            $this->encryptor = new Encryptor(
                corpId: $this->getAccount()->getCorpId(),
                token: $this->getAccount()->getToken(),
                aesKey: $this->getAccount()->getAesKey(),
            );
        }

        return $this->encryptor;
    }

    public function setEncryptor(Encryptor $encryptor): static
    {
        $this->encryptor = $encryptor;

        return $this;
    }

    /**
     * @throws \ReflectionException
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidArgumentException
     * @throws \Throwable
     */
    public function getServer(): Server|ServerInterface
    {
        if (!$this->server) {
            $this->server = new Server(
                account: $this->getAccount(),
                request: $this->getRequest(),
                encryptor: $this->getEncryptor()
            );
        }

        return $this->server;
    }

    public function setServer(ServerInterface $server): static
    {
        $this->server = $server;

        return $this;
    }

    public function getAccessToken(): AccessTokenInterface
    {
        if (!$this->accessToken) {
            $this->accessToken = new AccessToken(
                corpId: $this->getAccount()->getCorpId(),
                secret: $this->getAccount()->getSecret(),
                cache: $this->getCache(),
                httpClient: $this->getHttpClient(),
            );
        }

        return $this->accessToken;
    }

    public function setAccessToken(AccessTokenInterface $accessToken): static
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    public function createClient(): Client
    {
        return new Client($this->getHttpClient(), $this->getAccessToken());
    }

    public function getOAuth(): SocialiteProviderInterface
    {
        return (new WeWork(
            [
                'client_id' => $this->getAccount()->getCorpId(),
                'client_secret' => $this->getAccount()->getSecret(),
                'redirect_url' => $this->config->get('oauth.redirect_url'),
            ]
        ))->withApiAccessToken($this->getAccessToken()->getToken())
            ->scopes($this->config->get('oauth.scopes', ['snsapi_base']));
    }

    public function getTicket(): JsApiTicket
    {
        if (!$this->ticket) {
            $this->ticket = new JsApiTicket(
                corpId: $this->getAccount()->getCorpId(),
                cache: $this->getCache(),
                httpClient: $this->getClient(),
            );
        }

        return $this->ticket;
    }

    public function setTicket(JsApiTicket $ticket): static
    {
        $this->ticket = $ticket;

        return $this;
    }

    protected function getHttpClientDefaultOptions(): array
    {
        return \array_merge(
            ['base_uri' => 'https://qyapi.weixin.qq.com/',],
            (array)$this->config->get('http', [])
        );
    }
}
