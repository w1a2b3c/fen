<?php

namespace fast;

use app\common\library\Aliyun;
use think\Request;

/**
 * 版本检测和对比
 */
class Utils
{

	/**
	 * xml转数组
	 * @param string $xml
	 * @return array|bool|mixed
	 */
	public static function xml2array($xml)
	{
		if (empty($xml)) {
			return [];
		}
		$result = [];
		libxml_disable_entity_loader(true);
		if (preg_match('/(\<\!DOCTYPE|\<\!ENTITY)/i', $xml)) {
			return false;
		}
		$xmlObj = simplexml_load_string($xml);
		if ($xmlObj === false) {
			return $result;
		} else {
			$result = json_decode(json_encode($xmlObj), true);
			if (is_array($result)) {
				return $result;
			} else {
				return [];
			}
		}
	}

	/**
	 * 错误信息
	 * @param array $data
	 * @return bool
	 */
	public static function is_error($data)
	{
		if ($data == false || (is_array($data) && array_key_exists('errno', $data) && $data['errno'] != 0)) {
			return false;
		} else {
			return true;
		}
	}

	/**
	 * 替换字符串中的相对地址为绝对地址
	 * @param $src
	 * @param string $host 可以指定地址，如不设置默认为系统地址
	 * @return string
	 */
	public static function toMedia($src, $host = '')
	{
		$lowerSrc = ltrim(strtolower($src), '/');
		if (strpos($lowerSrc, 'http://') === 0 || strpos($lowerSrc, 'https://') === 0) {
			return $src;
		}
		$host = !empty($host) ? $host : request()->domain();
		if (strpos($host, 'http') === false) {
			$host = 'http://' . $host;
		}
		if (strpos($lowerSrc, 'uploads') === 0) {
			return $host . '/' . ltrim($src, '/');
		}
		// 富文本正则替换
		return preg_replace('/(\=[\'|\"])(\/?uploads\/)/i', '${1}' . $host . '/uploads/', $src, -1);
	}

	/**
	 * 验证身份证号
	 */
	public static function checkIdCard($idcard)
	{
		if (strlen($idcard) == 18) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * 验证姓名
	 */
	public static function checkUsername($username)
	{
		if (mb_substr($username, 0, 1, 'utf-8')) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * base64图片解码上传图片
	 * @param $thumb_base64
	 * @return string
	 * @throws \yii\db\Exception
	 */
	public static function base64pic($thumb_base64, $uid = 0)
	{
		//项目绝对路径
		$url = str_replace('\\', '/', ROOT_PATH . "public");
		if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $thumb_base64, $result)) {
			$type = $result[2];
			if (!in_array($type, ['png', 'jpeg', 'gif', 'jepg', 'jpg'])) {
				return false;
			}
			$strlen = strlen($thumb_base64);
			$img = base64_decode(str_replace($result[1], '', $thumb_base64));
			$new_file = $url . '/uploads/' . date('Ymd', time()) . '/';
			if (!file_exists($new_file)) {
				mkdir($new_file, 0777, true);
			}
			$info_file = date("YmdHis") . "_" . $uid . '_' . rand(10000, 99999) . '.' . "{$type}";
			$new_file = $new_file . $info_file;
			//数据库存储地址
			$db_file = '/uploads/' . date('Ymd', time()) . '/' . $info_file;
			$oss = Aliyun::getOss();
			$thumbUrl = $oss->uploadOs(trim($db_file, "/"), $img);
			if ($file = file_put_contents($new_file, $img)) {
				self::toAttachment($thumbUrl, $type, $strlen, $uid);
				return $thumbUrl;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	/**
	 * 图片存储 数据库记录
	 */
	public static function toAttachment($thumb, $type, $strlen = 0, $uid = 0)
	{
		$imgInfo = getimagesize($thumb);
		$params = array(
			'admin_id' => 0,
			'user_id' => (int)$uid,
			'filesize' => $strlen,
			'imagewidth' => $imgInfo[0],
			'imageheight' => $imgInfo[1],
			'imagetype' => $type,
			'imageframes' => 0,
			'mimetype' => $imgInfo['mime'],
			'url' => $thumb,
			'uploadtime' => time(),
			'storage' => 'aliyun',
			'sha1' => md5($thumb),
		);
		$attachment = model("attachment");
		$attachment->data(array_filter($params));
		$attachment->save();
	}

	/**
	 * 获取用户真实IP
	 * @return array|false|string
	 */
	public static function getIp()
	{
		$ip = $_SERVER['REMOTE_ADDR'];
		if (isset($_SERVER['HTTP_CDN_SRC_IP'])) {
			$ip = $_SERVER['HTTP_CDN_SRC_IP'];
		} elseif (isset($_SERVER['HTTP_CLIENT_IP']) && preg_match('/^([0-9]{1,3}\.){3}[0-9]{1,3}$/', $_SERVER['HTTP_CLIENT_IP'])) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && preg_match_all('#\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}#s', $_SERVER['HTTP_X_FORWARDED_FOR'], $matches)) {
			foreach ($matches[0] AS $xip) {
				if (!preg_match('#^(10|172\.16|192\.168)\.#', $xip)) {
					$ip = $xip;
					break;
				}
			}
		}
		return $ip;
	}

	/**
	 * 计算概率
	 * @param $proArr
	 * @param bool $isDayRose
	 * @return array
	 */
	public static function get_rand($proArr, $isDayRose = false, $gids = [])
	{
		$result = array();
		foreach ($proArr as $key => $val) {
			if ($isDayRose == true && @in_array($val['gid'], $gids)) {
				continue;
			}
			$arr[$key] = intval($val['chance'] * 1000);
		}
		// 概率数组的总概率
		$proSum = array_sum($arr);
		if ($proSum <= 0) {
			return $proArr[0];
		}
		asort($arr);
		// 概率数组循环
		foreach ($arr as $k => $v) {
			$randNum = mt_rand(1, $proSum);
			if ($randNum <= $v) {
				$result = $proArr[$k];
				break;
			} else {
				$proSum -= $v;
			}
		}
		return $result;
	}

	/**
	 * 生成随机字符串
	 * @param int $length 字符串长度
	 * @param bool $numeric true表示纯数字
	 * @return string
	 */
	public static function random($length, $numeric = false)
	{
		$seed = base_convert(md5(microtime() . $_SERVER['DOCUMENT_ROOT']), 16, $numeric ? 10 : 35);
		$seed = $numeric ? (str_replace('0', '', $seed) . '012340567890') : ($seed . 'zZ' . strtoupper($seed));
		if ($numeric) {
			$hash = '';
		} else {
			$hash = chr(rand(1, 26) + rand(0, 1) * 32 + 64);
			$length--;
		}
		$max = strlen($seed) - 1;
		for ($i = 0; $i < $length; $i++) {
			$hash .= $seed{mt_rand(0, $max)};
		}
		return $hash;
	}

	/**
	 * 兼容本地路径和绝对路径的图片
	 * @param $path
	 * @return string
	 */
	public static function toPath($path)
	{
		if (strpos($path, 'http') !== false) {
			return $path;
		} else {
			return ROOT_PATH . 'public' . $path;
		}
	}

	/**
	 * 数组 根据 数据中的 某个 字段 重构数据
	 */
	public static function toKeyArray($temp, $keyfield)
	{
		$rs = array();
		if (!empty($temp)) {
			foreach ($temp as $key => &$row) {
				if (isset($row[$keyfield])) {
					$rs[$row[$keyfield]] = $row;
				} else {
					$rs[] = $row;
				}
			}
		}
		return $rs;
	}

	/**
	 * 毫秒
	 */
	public static function msectime()
	{
		list($msec, $sec) = explode(' ', microtime());
		$msectime = (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
		return $msectime;
	}
	/**
	 * 订单号
	 * @param $type
	 * @param $id
	 * @return string
	 */
	public static function getOrderId($type, $id)
	{
		if (strlen($id) < 5) {
			$newId = str_pad($id, 5, "0", STR_PAD_LEFT);
		} else {
			$newId = substr($id, "-5");
		}
		return $type.date("ymdHis").$newId;
	}
	/**
	 * @desc 根据两点间的经纬度计算距离
	 * @param $lat1
	 * @param $lng1
	 * @param $lat2
	 * @param $lng2
	 * @return float
	 */
	public static function getDistance($lat1, $lng1, $lat2, $lng2) {
		$earthRadius = 6367000;
		$lat1 = ($lat1 * pi()) / 180;
		$lng1 = ($lng1 * pi()) / 180;
		$lat2 = ($lat2 * pi()) / 180;
		$lng2 = ($lng2 * pi()) / 180;
		$calcLongitude = $lng2 - $lng1;
		$calcLatitude = $lat2 - $lat1;
		$stepOne = pow(sin($calcLatitude / 2), 2) + cos($lat1) * cos($lat2) * pow(sin($calcLongitude / 2), 2);
		$stepTwo = 2 * asin(min(1, sqrt($stepOne)));
		$calculatedDistance = $earthRadius * $stepTwo;
		return round($calculatedDistance);
	}
    //百度坐标转高德（传入经度、纬度）
    public static function bd_decrypt($bd_lng,$bd_lat) {
        $X_PI = M_PI * 3000.0 / 180.0;
        $x = $bd_lng - 0.0065;
        $y = $bd_lat - 0.006;
        $z = sqrt($x * $x + $y * $y) - 0.00002 * sin($y * $X_PI);
        $theta = atan2($y, $x) - 0.000003 * cos($x * $X_PI);
        $gg_lng = $z * cos($theta);
        $gg_lat = $z * sin($theta);
        return array(
            "lng"=>$gg_lng,
            "lat"=>$gg_lat,
        );
    }
    //高德坐标转百度（传入经度、纬度）
    public static function bd_encrypt($gg_lng, $gg_lat) {
        $X_PI = M_PI * 3000.0 / 180.0;
        $x = $gg_lng;
        $y = $gg_lat;
        $z = sqrt($x * $x + $y * $y) + 0.00002 * sin($y * $X_PI);
        $theta = atan2($y, $x) + 0.000003 * cos($x * $X_PI);
        $bd_lng = $z * cos($theta) + 0.0065;
        $bd_lat = $z * sin($theta) + 0.006;
        return array(
            "lng"=>$bd_lng,
            "lat"=>$bd_lat,
        );
    }
}
