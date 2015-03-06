<?php namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class GetWorstData extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'getWorstData';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Command description.';

	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function fire()
	{
		$data = LineStamp::getWorstStamp();
		print_r($data);
		LineStamp::saveImgData($data);
	}

	/**
	 * Get the console command arguments.
	 *
	 * @return array
	 */
	protected function getArguments()
	{
		return [
			['example', InputArgument::REQUIRED, 'An example argument.'],
		];
	}

	/**
	 * Get the console command options.
	 *
	 * @return array
	 */
	protected function getOptions()
	{
		return [
			['example', null, InputOption::VALUE_OPTIONAL, 'An example option.', null],
		];
	}

}

class LineStamp{

	public static function getWorstStamp(){

		$base_url = 'https://store.line.me/stickershop/showcase/top_creators/ja?page=';
		$page = LineStamp::_getLastPage();

		$stamp_list = array();
		while(true){
			$url = $base_url.$page;
			$html_array = LineStamp::_getHtmlArray($url);

			if(LineStamp::_isNotFoundPage($html_array)){
				$page--;
				//最終ページを超えたらループ抜ける
				break;
			}

			$stamp_list = LineStamp::_getStampList($html_array);

			if(empty($stamp_list)){
				//リストが取得できなければエラー（LINE STORE仕様変更の可能性あり）
				print 'help1';
				exit;
			}

			print $page.'<br>';
			$page++;
		}

		if(empty($stamp_list[0])){
			//リストが取得できなければエラー（LINE STORE仕様変更の可能性あり）
			print 'help2';
			exit;
		}

		LineStamp::_saveLastPage($page);

		$worst_stamp = LineStamp::_getStampData($stamp_list[0]);

		return $worst_stamp;
	}

	private static function _getPageDataFilePath(){
		$filepath = storage_path('app/page.txt');
		return $filepath;
	}

	private static function _getLastPage(){
		$page = file_get_contents(LineStamp::_getPageDataFilePath());
		return $page;
	}

	private static function _saveLastPage($page){
		file_put_contents(LineStamp::_getPageDataFilePath(), $page);
	}

	private static function _getHtmlArray($url){

		//ステータスコードが失敗を意味する場合でもコンテンツを取得する
		$context = stream_context_create(array(
				'http' => array('ignore_errors' => true)
		));
		$html = file_get_contents($url, false, $context);

		$domDocument = new \DOMDocument();
		@$domDocument->loadHTML(mb_convert_encoding($html,'HTML-ENTITIES','auto'));
		$xmlString = $domDocument->saveXML();
		$xmlObject = simplexml_load_string($xmlString);

		$html_array = json_decode(json_encode($xmlObject), true);
		return $html_array;
	}

	private static function _isNotFoundPage($html_array){

		if(isset($html_array['body']['div']['div']['div']['div']['div']['section']['div']['h2'])){
			$tmp = $html_array['body']['div']['div']['div']['div']['div']['section']['div']['h2'];
			if($tmp == 'ページが開きません'){
				return true;
			}
		}
		return false;
	}

	private static function _getStampList($html_array){
		$tmp = array();
		if(isset($html_array['body']['div']['div']['div'][0]['div']['div']['section']['div']['ul']['li'])){
			$tmp = $html_array['body']['div']['div']['div'][0]['div']['div']['section']['div']['ul']['li'];
		}

		$stamp_list = array();
		if(isset($tmp['a'])){
			//ページ内に1件だけだった場合の対応
			$stamp_list[0] = $tmp;
		}else{
			//逆順にソートして添え字振り直し
			krsort($tmp);
			$stamp_list = array_values($tmp);
		}

		return $stamp_list;
	}

	private static function _getStampData($stamp_data){
		$data = array();
		$data['key'] = str_replace(array('/stickershop/product/', '/ja'), '', $stamp_data['a']['@attributes']['href']);
		$data['name'] = $stamp_data['a']['div'][1];
		$data['url'] = 'https://store.line.me'. $stamp_data['a']['@attributes']['href'];

		$html_array = LineStamp::_getHtmlArray($data['url']);
		$data['main_img'] = $html_array['body']['div']['div']['div'][0]['div']['div']['section']['div'][0]['div']['div'][0]['img']['@attributes']['src'];
		$data['description'] = $html_array['body']['div']['div']['div'][0]['div']['div']['section']['div'][2]['div']['p'][0];

		return $data;
	}

	public static function saveImgData($data){
		$img_data = file_get_contents($data['main_img']);
		$img_path = public_path('img/stamp/'.$data['key'].'.png');
		file_put_contents($img_path, $img_data);
	}

}
