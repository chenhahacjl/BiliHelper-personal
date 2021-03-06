<?php

/**
 *  Website: https://mudew.com/
 *  Author: Lkeme
 *  License: The MIT License
 *  Email: Useri@live.cn
 *  Updated: 2019 ~ 2020
 */

namespace BiliHelper\Plugin;

use BiliHelper\Core\Log;
use BiliHelper\Core\Curl;
use BiliHelper\Util\TimeLock;

class AwardRecord
{
    use TimeLock;
    private static $raffle_lock = 0;
    private static $raffle_list = [];
    private static $anchor_lock = 0;
    private static $anchor_list = [];
    private static $gift_lock = 0;
    private static $gift_list = [];


    public static function run()
    {
        if (self::getLock() > time()) {
            return;
        }
        if (self::$anchor_lock < time()) {
            self::anchorAward();
        }
        if (self::$raffle_lock < time()) {
            self::raffleAward();
        }
        // if (self::$gift_lock < time()) {
        //     self::giftAward();
        // }
        self::setLock(5 * 60);
    }


    /**
     * @use 获取天选时刻中奖纪录
     */
    private static function anchorAward()
    {
        $payload = [
            'page' => '1',
        ];
        $url = 'https://api.live.bilibili.com/xlive/lottery-interface/v1/Anchor/AwardRecord';
        $raw = Curl::get($url, Sign::api($payload));
        $de_raw = json_decode($raw, true);
        // 防止异常
        if (!isset($de_raw['data']) || !isset($de_raw['data']['list'])) {
            Log::warning("获取天选时刻获奖记录错误: " . json_encode($de_raw, JSON_FORCE_OBJECT));
            self::$anchor_lock = time() + 1 * 60 * 60;
            return;
        }
        foreach ($de_raw['data']['list'] as $anchor) {
            $win_time = strtotime($anchor['end_time']);  //礼物时间
            $day = ceil((time() - $win_time) / 86400);  //60s*60min*24h
            // 去重
            if (in_array($anchor['id'], self::$anchor_list)) {
                continue;
            }
            // 范围
            if ($day <= 2) {
                $info = $anchor['award_name'] . 'x' . $anchor['award_num'];
                Log::notice("天选时刻于" . $anchor['end_time'] . "获奖: {$info} ,请留意查看...");
                Notice::push('anchor', $info);
            }
            array_push(self::$anchor_list, $anchor['id']);
        }
        self::$anchor_lock = time() + 6 * 60 * 60;
    }


    /**
     * @use 获取实物抽奖中奖纪录
     */
    private static function raffleAward()
    {
        $payload = [
            'page' => '1',
            'month' => '',
        ];
        $url = 'https://api.live.bilibili.com/lottery/v1/award/award_list';
        $raw = Curl::get($url, Sign::api($payload));
        $de_raw = json_decode($raw, true);

        // 防止异常
        if (!isset($de_raw['data']) || !isset($de_raw['data']['list']) || $de_raw['code']) {
            Log::warning("获取实物奖励获奖记录错误: " . $de_raw['msg']);
            self::$raffle_lock = time() + 1 * 60 * 60;
            return;
        }
        foreach ($de_raw['data']['list'] as $raffle) {
            $win_time = strtotime($raffle['create_time']);  //礼物时间
            $day = ceil((time() - $win_time) / 86400);  //60s*60min*24h
            // 去重
            if (in_array($raffle['id'], self::$raffle_list)) {
                continue;
            }
            // 范围
            if ($day <= 2 && empty($raffle['update_time'])) {
                $info = $raffle['gift_name'] . 'x' . $raffle['gift_num'];
                Log::notice("实物奖励于" . $raffle['end_time'] . "获奖: {$info} ,请留意查看...");
                Notice::push('raffle', $info);
            }
            array_push(self::$raffle_list, $raffle['id']);
        }
        self::$raffle_lock = time() + 6 * 60 * 60;
    }


    /**
     * @use 获取活动礼物中奖纪录
     */
    private static function giftAward()
    {
        $payload = [
            'type' => 'type',
            'raffleId' => 'raffle_id'
        ];
        // Web V3 Notice
        $url = 'https://api.live.bilibili.com/xlive/lottery-interface/v3/smalltv/Notice';
        // 请求 && 解码
        $raw = Curl::get($url, Sign::api($payload));
        $de_raw = json_decode($raw, true);
    }
}