<?php

/**
 * Created by PhpStorm.
 * User: Valeriy Vasilev
 * Date: 08.08.2017
 * Time: 19:07
 * Description: Class for executing HTTP - requests to the Java server
 */

namespace common\models;

use common\models\services\ConfigApi;
use yii\base\Model;
use Yii;

class Order extends Model
{

    public $price;
    public $amount;
    public $total;
    public $type;
    public $currencyPair;

    /**
     * Description: set HTTP request to server
     * @param string $url
     * @param string $method
     * @param array $params
     * @return mixed|\Psr\Http\Message\ResponseInterface
     */
    private function setGuzzlerequest($url = '', $method = 'GET', $params = [])
    {
        $baseUrl = ConfigApi::getBaseurl();
        $client = new \GuzzleHttp\Client();
        if ($method == 'PUT') {
            $res = $client->put($baseUrl . $url, $params);
        }
        if ($method == 'GET') {
            $res = $client->get($baseUrl . $url);
        }
        if ($method == 'POST') {
            $res = $client->post($baseUrl . $url);
        }
        return $res;
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['price', 'trim'],
            ['price', 'required'],
            ['amount', 'trim'],
            ['amount', 'required'],
            ['total', 'trim'],
            ['total', 'required'],
            ['type', 'trim'],
            ['type', 'required'],
            ['currencyPair', 'trim'],
            ['currencyPair', 'required'],
        ];
    }

    /**
     * @return mixed
     */
    public function send()
    {
        $aceRid = Yii::$app->user->id;
        $params = [
            'json' =>
                ["aceRid" => $aceRid,
                    "type" => $this->type,
                    "rate" => $this->price,
                    "currencyPair" => $this->currencyPair,
                    "amount" => $this->amount,
                ]
        ];
        try {
            $res = $this->setGuzzlerequest('/stock/order', 'PUT', $params);
            return $res->getBody();
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            return \GuzzleHttp\json_decode($e->getResponse()->getBody(true));
        }
    }

    /**
     * @return mixed
     */
    public function getBaseurl()
    {
        return $this->base_url;
    }

    /**
     * get Lowest price for currency pair
     * @param $currencyPair
     * @return int
     */
    public function getLowestprice($currencyPair)
    {
        try {
            $res = $this->setGuzzlerequest('/stock/order/ask/lowest?currencyPair=' . $currencyPair, 'GET', []);
            $body = \GuzzleHttp\json_decode($res->getBody());
            return ($body[0]->rate) ? $body[0]->rate : 0;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            return \GuzzleHttp\json_decode($e->getResponse()->getBody(true));
        }

    }

    /**
     * get Highest price for currency pair
     * @param $currencyPair
     * @return int
     */
    public function getHighestprice($currencyPair)
    {
        try {
            $res = $this->setGuzzlerequest('/stock/order/bid/highest?currencyPair=' . $currencyPair, 'GET', []);
            $body = \GuzzleHttp\json_decode($res->getBody());
            return ($body[0]->rate) ? $body[0]->rate : 0;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            return \GuzzleHttp\json_decode($e->getResponse()->getBody(true));
        }
    }

    /**
     * Get information about user orders
     * @return bool
     */
    public function getUserorder()
    {
        $aceId = Yii::$app->user->id;
        if ($aceId === NULL)
            return false;
        try {
            $res = $this->setGuzzlerequest('/stock/' . $aceId . '/order/active?aceRid=' . $aceId, 'GET', []);
            return \GuzzleHttp\json_decode($res->getBody());
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            return \GuzzleHttp\json_decode($e->getResponse()->getBody(true));
        }
    }

    /**
     * Cancel order
     * @param $rid
     * @return bool
     */
    public function cancelOrder($rid)
    {
        try {
            $res = $this->setGuzzlerequest('/stock/order/' . $rid . '/cancel', 'PUT', []);
            return \GuzzleHttp\json_decode($res->getBody());
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            return \GuzzleHttp\json_decode($e->getResponse()->getBody(true));
        }
    }

    /**
     * Validate date
     * @param $date
     * @param string $format
     * @return bool
     */
    public function validateDate($date, $format = 'Y-m-d')
    {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) == $date;
    }

    /**
     * get all last executed orders
     * @param $request
     * @return bool
     */
    public function getLastexecutedorders($request)
    {
        $aceId = User::find()->select('userNid')->asArray()->where(['id' => Yii::$app->user->id])->one()["userNid"];
        $pair = strtolower($request["base_currency"] . '_usdt');
        if (!$this->validateDate($request['from_date']) && !$this->validateDate($request['to_date'])) {
            $d = new \DateTime();
            $request['to_date'] = $d->format('Y-m-d');
            $d->modify('-' . ($d->format('N') - 1) . ' day');
            $request['from_date'] = $d->format('Y-m-d');
        }
        try {
            $res = $this->setGuzzlerequest('/stock/' . $aceId . '/order?currencyPair=' . $pair . '&fromTs=' . $request['from_date'] . '&toTs=' . $request['to_date'], 'GET', []);
            return \GuzzleHttp\json_decode($res->getBody());
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            return \GuzzleHttp\json_decode($e->getResponse()->getBody(true));
        }
    }

    /**
     * find all executed orders
     * @param $pair
     * @return mixed
     */
    public function getOrdersExecuted($pair)
    {
        $pair = strtolower($pair);
        try {
            $res = $this->setGuzzlerequest('/stock/order/executed?currencyPair=' . $pair, 'GET', []);
            return \GuzzleHttp\json_decode($res->getBody());
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            return \GuzzleHttp\json_decode($e->getResponse()->getBody(true));
        }
    }

    /**
     * find all active orders
     * @param $pair
     * @return mixed
     */
    public function findActive($pair)
    {
        try {
            $res = $this->setGuzzlerequest('/stock/market/' . $pair . '/sell', 'GET', []);
            if ($res->getStatusCode() == 200) {
                $body = \GuzzleHttp\json_decode($res->getBody());
                $body->asksSum = 0;
                foreach ($body->asks as $k => $v) {
                    $body->asksSum += $body->asks[$k][2];
                }
            }
            $res = $this->setGuzzlerequest('/stock/market/' . $pair . '/buy', 'GET', []);
            if ($res->getStatusCode() == 200) {
                $bod_buy = \GuzzleHttp\json_decode($res->getBody());
                $body->bidsSum = 0;
                foreach ($bod_buy->bids as $k => $v) {
                    $body->bids[$k][0] = $bod_buy->bids[$k][0];
                    $body->bids[$k][1] = $bod_buy->bids[$k][1];
                    $body->bids[$k][2] = $bod_buy->bids[$k][2];
                    $body->bidsSum += $bod_buy->bids[$k][2];
                }
            }
            $body->sum = $body->asksSum + $body->bidsSum;
            return \GuzzleHttp\json_encode($body);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            return \GuzzleHttp\json_decode($e->getResponse()->getBody(true));
        }
    }

    /**
     * find all executed orders by array format
     * @param $pair_array
     * @return array
     */
    public function getLastexecutedByArray($pair_array = [])
    {
        $executed_arr = [];
        foreach ($pair_array as $value) {
            $list_orders = $this->getLastexecutedorders($value);
            if (isset($list_orders)) {
                foreach ($list_orders as $k => $v) {
                    $executed_arr[$value][$k]['type'] = $v->type;
                    $executed_arr[$value][$k]['rate'] = $v->rate;
                    $executed_arr[$value][$k]['amount'] = $v->startAmount;
                    $executed_arr[$value][$k]['executed_date'] = date('H:i:s d.m.Y', strtotime($v->executedTs));
                    $executed_arr[$value]['sum'] += $v->rate * $v->startAmount;
                }
            }
        }
        return $executed_arr;
    }


    /**
     * get last execude by full users
     * @param $pair_array
     * @return array
     */
    public function getOrdersByArray($pair_array)
    {
        $executed_arr = [];
        foreach ($pair_array as $value) {
            $list_orders = $this->getOrdersExecuted($value);
            if (isset($list_orders)) {
                foreach ($list_orders as $k => $v) {
                    $executed_arr[$value][$k]['type'] = $v->type;
                    $executed_arr[$value][$k]['rate'] = $v->rate;
                    $executed_arr[$value][$k]['amount'] = $v->startAmount;
                    $executed_arr[$value][$k]['executed_date'] = date('H:i:s d.m.Y', strtotime($v->executedTs));
                    $executed_arr[$value]['sum'] += $v->rate * $v->startAmount;
                }
            }
        }
        return $executed_arr;
    }

    /**
     * @param $currencyPair
     * @return mixed
     */
    public function getOrderActive($currencyPair)
    {
        try {
            $res = $this->setGuzzlerequest('/stock/order/active/' . $currencyPair, 'GET', []);
            $body = \GuzzleHttp\json_decode($res->getBody());
            $data['sum'] = 0;
            $data['body'] = $body;
            foreach ($data['body'] as $k => $v) {
                $data['body'][$k]->createdTsFormat = date('H:i:s d.m.Y', strtotime($v->createdTs));
                $data['sum'] += $v->amount;
            }
            return \GuzzleHttp\json_encode($data);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            return $e->getResponse()->getBody()->getContents();
        }
    }

}
