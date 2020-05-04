<?php
class RedisAof
{
	private $redis = [];
	private $statsArr = [];
	private $rewritePercentage = 1;

	public function __construct($configs)
	{
		foreach ($configs as $config){
			$redis = new Redis();
			$redis->connect($config['host'], $config['port'], $config['timeout']);
			if ($config['auth']){
				$redis->auth($config['auth']);
			}
			$this->redis[] = $redis;
		}

	}

	public function aof()
	{

		foreach ($this->redis as $redis){
			$statsArr = $this->getStats($redis);

			if (!$this->checkAof($statsArr)){
				continue;
			}

			if (!$this->checkRewritePercentage($statsArr)){
				continue;
			}

			if (!$this->checkCanAof($statsArr)){
				continue;
			}
			while (true){
				$this->doAof($redis);
				if(!$this->checkAofDoing($statsArr)){
					break;
				}
			}
		}
	}

	protected function doAof($redis)
	{
		$redis->rawCommand('bgrewriteaof');
	}

	protected function checkAofDoing($statsArr)
	{
		if ($statsArr['aof_rewrite_in_progress'] && $statsArr['aof_current_rewrite_time_sec'] != -1){
			return true;
		}
	}

	protected function checkCanAof($statsArr)
	{
		if (!$statsArr['rdb_bgsave_in_progress']){
			return true;
		}
		if ($statsArr['rdb_current_bgsave_time_sec'] == -1){
			return true;
		}
		if ($statsArr['aof_rewrite_scheduled']){
			return false;
		}
	}

	protected function checkRewritePercentage($statsArr)
	{
		$rate = ($statsArr['aof_current_size'] - $statsArr['aof_base_size']) / floatval($statsArr['aof_base_size']);
		return $rate >= $this->rewritePercentage;
	}

	protected function checkAof($statsArr)
	{
		return $statsArr['aof_enabled'];
	}

	protected function getStats($redis)
	{
		$res = $redis->rawCommand('info', 'persistence');
		$res = explode("\r\n", $res);

		$statsArr = [];
		foreach ($res as $item){
			if(count($arr = explode(':', $item)) > 1){
				$statsArr[$arr[0]] = $arr[1];
			}
		}
		return $statsArr;
	}

}

$configs = [
	[
		'host' => 'localhost',
		'port' => 6379,
		'timeout' => 10,
		'auth' => '',
	],
];
$obj = new RedisAof($configs);
$obj->aof();


?>
