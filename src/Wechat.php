<?php
namespace Wechat;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Symfony\Component\Routing\Annotation\Route;

class Wechat
{
    private static $client;     //guzzle Client

    private $appid;
    private $secret;
    private $token;

    /**
     * @param string $appid appid
     * @param string $secret secret
     * @param string|false|null $token token|禁用自动获取token|自动获取token
     */
    public function __construct($appid, $secret, $token = null)
    {
        $this->appid = $appid;
        $this->secret = $secret;
        $this->token = $token;
        if (is_null($token)) {
            $this->token = $this->token();
        }
        if (empty(self::$client)) {
            self::$client = new Client(array(
                'timeout' => 2.0
            ));
        }
    }

    /**
     * 获取用户code并跳转至redirect_uri
     * @param string $redirectUri 跳转uri
     * @param boolean $scope 是否获取用户详细信息
     * @param mixed $state 附加参数
     * 
     * @return string $url 生成的url
     */
    function code($redirectUri, $scope = true, $state = '')
    {
        $scope = $scope ? 'snsapi_userinfo' : 'snsapi_base';
        $url = 'https://open.weixin.qq.com/connect/oauth2/authorize?%s#wechat_redirect';
        $params = array(
            'appid' => $this->appid,
            'redirect_uri' => urlencode($redirectUri),
            'response_type' => 'code',
            'scope' => $scope,
            'state' => $state,
        );
        $url = sprintf($url, http_build_query($params));
        return $url;
    }

    /**
     * 获取access_token、openid
     * 
     * @param string $code 用户code
     * 
     * @return array $rst 用户信息
     */
    function base($code)
    {
        $url = 'https://api.weixin.qq.com/sns/oauth2/access_token?%s';
        $params = array(
            'appid' => $this->appid,
            'secret' => $this->secret,
            'code' => $code,
            'grant_type' => 'authorization_code',
        );
        $url = sprintf($url, http_build_query($params));
        $response = self::$client->get($url);
        $code = $response->getStatusCode();
        if ($code != '200') {
            throw new \Exception('服务不可用');
        }
        $rst = json_decode($response->getBody()->getContents(), true);
        return $rst;
    }

    /**
     * 验证是否关注
     * @param  string $access_token access_token
     * @param  string $openid       用户openid
     * @return boolean              是否关注
     */
    function sub($accessToken, $openid)
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/user/info?%s';
        $params = array(
            'access_token' => $accessToken,
            'openid' => $openid,
            'lang' => 'zh_CN',
        );
        $url = sprintf($url, http_build_query($params));
        $response = self::$client->get($url);
        $code = $response->getStatusCode();
        if ($code != '200') {
            throw new \Exception('服务不可用');
        }
        $rst = json_decode($response->getBody()->getContents(), true);
        
        if($rst['subscribe'] == 1)
            return true;
        else
            return false;
    }

    /**
     * 获取用户信息
     * @param string $accessToken access_token
     * @param string $openid openid
     * 
     * @return array $array
     */
    function info($accessToken, $openid)
    {
        $url = 'https://api.weixin.qq.com/sns/userinfo?%s';
        $params = array(
            'access_token' => $accessToken,
            'openid' => $openid,
            'lang' => 'zh_CN',
        );
        $url = sprintf($url, http_build_query($params));
        $response = self::$client->get($url);
        $code = $response->getStatusCode();
        if ($code != '200') {
            throw new \Exception('服务不可用');
        }
        $rst = json_decode($response->getBody()->getContents(), true);
        return $rst;
    }

    /**
     * 获取access_token
     * 
     */
    function token()
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/token?%s';
        $params = array(
            'grant_type' => 'grant_type',
            'appid' => $this->appid,
            'secret' => $this->secret,
        );
        $url = sprintf($url, http_build_query($params));
        $response = self::$client->get($url);
        $code = $response->getStatusCode();
        if ($code != '200') {
            throw new \Exception('服务不可用');
        }
        $rst = json_decode($response->getBody()->getContents(), true);

        if ( ! isset($rst['errcode'])) {
            return $rst['access_token'];
        } else {
            throw new \Exception('token获取错误：' . json_encode($rst));
        }
    }
    
    /**
     * 发送模板消息
     * 参数列表
     *  string           $openid        [用户openid]
     *  string           $token         [服务器access_token]
     *  string           $template_id   [模板id]
     *  string           $detail_url    [点击打开的url]
     *  array            $data          [发送的信息]
        data => array(
            "first"=> array(
                "value"=>"您已为该微信绑定学号",
                "color"=>"#173177"
            ),
            "keyword1"=>array(
                "value"=>$acc,
                "color"=>"#173177"
            ),
            "keyword2"=> array(
                "value"=>date('Y-m-d H:i:s'),
                "color"=>"#173177"
            ),
            "remark"=>array(
                "value"=>"此微信账号查成绩无需手动填写密码（密码已修改除外）",
                "color"=>"#173177"
            )
        )
     */
    function teminfo($openid, $token, $template_id, $detail_url, $data)
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=' . $token;
        $data = array(
            'touser'      => $openid,
            'template_id' => $template_id,
            'url'         => $detail_url,
            'data'        => $data
        );
        $cookie = '';
        $result = json_decode(curl($url, $cookie, json_encode($data)), true);
        if($result['errcode'] === 0)
        {
            return true;
        }
        else
        {
            return $result;
        }
    }

}