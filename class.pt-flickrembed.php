<?php
class PTFLICKREMBED
{
	public $key 				= 'pt-flickrembed';
	public $debug 				= false;
	public $imageSrcAttribute 	= 'data-original';
	public $memory_limit		= 0;
	public $maxImageSize 		= 2560;
	public $maxThumbSize 		= 1024;
	public $maxFullSize 		= 2560;
	public $screenSize 		= -1;
	public $flickrSizes 		= array('o', 'l', 'c', 'z', 'm', 'n', 's', 'q', 't', 'sq');
	public $imageurl			= '';
	public $upload_dir 		= '';
	public $storagepath		= '/flickr/%1$s/%2$s_%3$s%4$s.jpg';
	public $flickrpath			= 'https://farm%1$s.static.flickr.com/%2$s/%3$s_%4$s%5$s.jpg';
	public $image_tag			= '<img %1$s="%2$s" alt="%3$s" %4$s />';
	public $image_wrapper_tag	= '<div class="wp-caption alignnone pt-flickrembed flickr size-%1$s">%2$s</div>';
	public $flickrfolder		= '/flickr/%1s/';
	public $atts 				= array();
	public $image_data 		= null;
	public $images 			= null;
	public $logfile			= '';
	public $flickrUserName		= '';
	public $cache_folder_path	= '';
	public $cache_key			= '_UNDEFINED_CACHE_FILE_NAME.json';
	public $config 			= array();

	/////////////////////////////////////////////

	public function __construct()
	{
		$this->config = array(
			'flickr_key' => esc_attr(get_option('flickr_key')),
			'flickr_secret' => esc_attr(get_option('flickr_secret')),
			'flickr_userid' => esc_attr(get_option('flickr_userid'))
		);

		if (!empty($this->config['flickr_key']) && !empty($this->config['flickr_secret']) && !empty($this->config['flickr_userid'])) {
			add_action('wp_enqueue_scripts', array(&$this,'add_scripts'));
			add_shortcode('flickr', array(&$this, 'parse'));
			//add_action('admin_menu', array(PTFLICKREMBED,'wpadmin'));
	
			$this->upload_dir = wp_upload_dir();

			@mkdir($this->upload_dir['basedir'].'/flickr', 0755, true);
			@mkdir($this->upload_dir['basedir'].'/flickr_backup', 0755, true);

			$this->backupfolder = $this->upload_dir['basedir'].'/fromflickr';
			$this->backupfolderURL = $this->upload_dir['baseurl'].'/fromflickr';
		}
	}

	public function parse($atts, $content = null)
	{
		$this->debug = is_user_logged_in() || $_GET['pt-flickrembed-force']==1;

		$atts=shortcode_atts(array(
			'id'				=> '',
			'link'				=> '',
			'size'				=> 'c',
			'align'				=> '',
			'title'				=> '',
			'buttontext'		=> 'Load images from Flickr',
			'order' 			=> 'date-taken-desc', // date-posted-asc, date-posted-desc, date-taken-asc, date-taken-desc, interestingness-desc, interestingness-asc, relevance
			'limit'				=> '0',
			'mode'				=> 'html',
			'per_page'			=> '40',
			'tags'				=> '',
			'text_pre'			=> '',
			'text_post'			=> '',
			'useDataSrc'		=> false
		), $atts);

		return $this->generate($atts);
	}

	private function set_memory_limit()
	{
		$this->memory_limit = ini_get('memory_limit');
		ini_set('memory_limit', '1024M');
	}

	private function reset_memory_limit()
	{
		if ($this->memory_limit > 0) {
			ini_set('memory_limit', $this->memory_limit);
		}
	}

	private function makeLogFile()
	{
		$logDir = __DIR__.'/log/';
		@mkdir($logDir, 0755, true);
		$this->logfile = $logDir.$this->key.'.'.date('Ym').'.log';
	}

	private function logIt($content, $speciallog = '')
	{
		if ($speciallog !== '') {
			@file_put_contents(__DIR__.'/log/' .$speciallog. '.' .date('Ym'). '.log', date('Y-m-d H:i:s').chr(9).$content.PHP_EOL, FILE_APPEND);
		} else {
			if (empty($this->logfile)) {
				$this->makeLogFile();
			}
			@file_put_contents($this->logfile, date('Y-m-d h:i:s').chr(9).$content.PHP_EOL, FILE_APPEND);
		}
	}


	public function add_scripts()
	{
		//wp_enqueue_script('collagescript', plugins_url( $this->key.'/collageplus.min.js' , dirname(__FILE__) ), null, '1.0.2', false);
		//wp_enqueue_script('jquery.grid-a-licious', plugins_url( $this->key.'/jquery.grid-a-licious.min.js' , dirname(__FILE__) ), null, '3.0.1', false);
		wp_enqueue_script($this->key.'_init', plugins_url($this->key.'/' .$this->key. '.init.js', dirname(__FILE__)), array('masonry','masonry-init'), '1.0.5', true);
	}

	private function resetFileRights($path)
	{
		$last_line = @system("/usr/bin/permission ".$path." -type d -group apache -exec chmod 777 {} \\;", $retval);
		$last_line = @system("/usr/bin/permission ".$path." -type f -group apache -exec chmod 777 {} \\;", $retval);
		$do_chmod = @chmod($path, 0777);
	}

	private function getRemoteFileContents($url)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$contents = curl_exec($ch);
		//$response = curl_getinfo($ch);
		curl_close($ch);
		return $contents;
	}

	private function writeImageDataToCache()
	{
		set_transient($this->cache_key, serialize($this->image_data), DAY_IN_SECONDS);
		/*
				$this->dump($this->cache_folder_path.$this->cache_key);
				$cache_file = @fopen($this->cache_folder_path.$this->cache_key, "w+");
				if($cache_file){
					fwrite($cache_file,$this->image_data,strlen($this->image_data));
					fclose($cache_file);
				}
		*/
	}

	private function readImageDataFromCache()
	{
		$cached_data = get_transient($this->cache_key);
		
		if ($cached_data) {
			$this->image_data = unserialize($cached_data);
		} else {
			echo '
			
			
			
			
			<!-- PTF DATA N/A FOR ' .$this->cache_key. ' -->';
		}

		/*
				$file = $this->cache_folder_path.$this->cache_key;
				if(file_exists($file)){
					$file_modified = filemtime($file);
					if(intval($file_modified)>0){
						$file_age = strtotime('today 00:00:01')-$file_modified;
						if($file_age<86400){
							$this->image_data = @file_get_contents($file);
							$this->images = json_decode($this->image_data,TRUE);
						}else{
							// delete old cached files
							@unlink($file);
						}
					}
				}
		*/
	}

	private function setCacheKey()
	{
		$this->cache_key = $this->atts['id'] . ($this->atts['size']!='' ? $this->atts['size'] : 'std');
	}

	private function setCacheFolderPath()
	{
		$this->cache_folder_path = $_SERVER['DOCUMENT_ROOT'].'/'.get_option('upload_path').'/'.$this->key.'/';
		if (!is_dir($this->cache_folder_path)) {
			if (!@mkdir($this->cache_folder_path, 0755, true)) {
				return '<p>The cache folder for ' .$this->key. ' could not be created.</p>';
			}
			$this->resetFileRights($this->cache_folder_path);
		}
	}

	private function readSingleImageDataFromFlickr()
	{
		//	fetch data from flickr API and cache it locally
		$FlickrRequestString = 'https://api.flickr.com/services/rest/?method=flickr.photos.getInfo&format=json&nojsoncallback=1&api_key='.$this->config['flickr_key'].'&secret='.$this->config['flickr_secret'].'&photo_id='.$this->atts['id'];
		
		if (($this->image_data = $this->getRemoteFileContents($FlickrRequestString))) {
			$this->images = json_decode($this->image_data, true);
			if ($this->images['stat']=='ok') {
				$this->writeImageDataToCache();
			}
		}
	}

	private function checkUrlHeaders($path)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $path);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HEADER, 1);
		curl_setopt($ch, CURLOPT_NOBODY, 1);
		$c = curl_exec($ch);
		return curl_getinfo($ch, CURLINFO_HTTP_CODE);
	}

	private function fetchFileFromFlickr($data, $size, $storagepath)
	{
		$this->set_memory_limit();

		$flickrFolder = sprintf(
			$this->flickrfolder,
			$data['photo']['server']
		);

		@mkdir($this->upload_dir['basedir'].$flickrFolder, 0755, true);

		$flickrURL = sprintf(
			$this->flickrpath,
			$data['photo']['farm'],
			$data['photo']['server'],
			$data['photo']['id'],
			$data['photo']['secret'],
			($size!==''?'_'.$size:'')
		);

		$responsecode = $this->checkUrlHeaders($flickrURL);

		if ($responsecode == 200) {
			if (!copy($flickrURL, $storagepath)) {
				$this->logIt('Unable to store “' .$flickrURL. '” at “' .$storagepath. '”');
			} else {
				$this->logIt('Stored “' .$flickrURL. '” at “' .$storagepath. '”', 'remotecopy');
			}
		}

		$this->reset_memory_limit();
	}

	private function parseFlickrImageUrl($string)
	{
		return $string;
		return preg_replace('~http[s?]://farm[0-9]\.staticflickr\.com~', 'https://' .$_SERVER['HTTP_HOST']. '/wp-content/uploads/flickr', $string);
	}


	private function IDtoURL(&$data, $size, $single = false)
	{
		if ($single) {
			$localimagepath = sprintf(
				$this->storagepath,
				$data['photo']['server'],
				$data['photo']['id'],
				$data['photo']['secret'],
				($size!==''?'_'.$size:'')
			);

			// first, make sure that the file has been pulled from flickr

			if (!file_exists($this->upload_dir['basedir'].$localimagepath)) {
				$this->fetchFileFromFlickr($data, $size, $this->upload_dir['basedir'].$localimagepath);
			}

			// make a second cURL request to ensure that the file has been copied over successfully
			// if not, then use the flickr URL this time.

			if (file_exists($this->upload_dir['basedir'].$localimagepath)) {
				$this->imageurl = $this->upload_dir['baseurl'].$localimagepath;

			/*$imageSize = @getimagesize($this->upload_dir['basedir'].$localimagepath);

			if($imageSize){
				$data['photo']['calculated_width'] = $imageSize[0];
				$data['photo']['calculated_height'] = $imageSize[1];
				$data['photo']['html_size_attributes'] = $imageSize[3];
			}else{
				$data['photo']['calculated_width'] = null;
				$data['photo']['calculated_height'] = null;
				$data['photo']['html_size_attributes'] = '';
			}*/
			} else {
				$this->getLargestImage();
			}
		} else {
			$localimagepath = sprintf(
				$this->storagepath,
				$data->photo->server,
				$data->photo->id,
				$data->photo->secret,
				($size!==''?'_'.$size:'')
			);

			if (!file_exists($this->upload_dir['basedir'].$localimagepath)) {
				$this->fetchFileFromFlickr($data, $size, $this->upload_dir['basedir'].$localimagepath);
			}

			if (file_exists($this->upload_dir['basedir'].$localimagepath)) {
				$this->imageurl = $this->upload_dir['baseurl'].$localimagepath;

			/*$imageSize = @getimagesize($this->upload_dir['basedir'].$localimagepath);

			if($imageSize){
				$data['photo']['calculated_width'] = $imageSize[0];
				$data['photo']['calculated_height'] = $imageSize[1];
				$data['photo']['html_size_attributes'] = $imageSize[3];
			}else{
				$data['photo']['calculated_width'] = null;
				$data['photo']['calculated_height'] = null;
				$data['photo']['html_size_attributes'] = '';
			}*/
			} else {
				$this->getLargestImage();
			}
		}
	}

	public function generate($atts)
	{
		//require_once("xml.lib.php");

		$this->atts = $atts;

		$this->makeLogFile();
		
		$this->atts['order'] = strtolower($this->atts['order']);

		$this->atts['useDataSrc'] = intval($this->atts['useDataSrc']);

		/*
				if($this->atts['useDataSrc']){
					$this->imageSrcAttribute='data-src';
				}
		*/

		$this->setCacheFolderPath();
		$this->setCacheKey();

		$cached_data = '';

		if (intval($this->atts['id'])>0) {
			return $this->view_single();
		} else {
			return $this->view_list($this->atts);
		}
	}

	public function view_single()
	{
		if ($this->atts['mode'] !== 'src') {
			$this->backupLargestFlickrFile($this->atts['id']);

			$html = '';

			$transient_name = md5('flickr-view_single-' . $this->atts['id'] . '.cachify');

			if (false === ($html = get_transient($transient_name))) {
				$html = wp_oembed_get('https://www.flickr.com/photos/mhowells/'.$this->atts['id'].'/', array('width' => 640));
		
				if (!empty($html)) {
					$html = sprintf(
						$this->image_wrapper_tag,
						$this->attr['size'],
						$html
					);
						
					set_transient($transient_name, $html, WEEK_IN_SECONDS);
				}
			}
			
			return $html;
		}

		//return $this->getFromFlickrWithOembed();

		$this->readImageDataFromCache();

		if (!$this->image_data || $this->debug) {
			$this->readSingleImageDataFromFlickr();
		}
		
		$this->images = json_decode($this->image_data, true);

		$out=array();

		if ($this->images) {
			if ($this->images['stat']=='ok' && $this->images['photo']['media']=='photo') {
				$this->photo = $this->images['photo'];
				
				switch ($this->atts['mode']) {
					case 'src':
						$this->IDtoURL($this->photo, $this->atts['size'], true);
						return $this->imageurl;
						break;
					
					default:
						$flickrHTML = $this->getFlickrHTMLViaOembed();
						break;
				}
				
				if ($this->atts['title']!=='') {
					$flickrHTML .= '<p class="wp-caption-text">'.$this->atts['title'].'</p>';
				}
				
				$out[] = $flickrHTML;

				/*
								if($this->atts['title']!=''){

									$this->IDtoURL($this->images,$this->atts['size'],true);

									if($this->atts['mode']=='src'){
										return $this->imageurl;
									}

									$link=($this->atts['link']!==''?$this->atts['link']:($this->atts['link']==false?'0':$this->images['photo']['urls']['url'][0]['_content']));

									if($this->images['photo']['calculated_width'] > 0 && $this->images['photo']['calculated_height']>0){
										$html_size_attributes = 'style="width:' .$this->images['photo']['calculated_width']. 'px;height:' .$this->images['photo']['calculated_height']. 'px"';
									}else{
										$html_size_attributes = '';
									}

									$image_tag = sprintf($this->image_tag,
										$this->imageSrcAttribute,
										$this->imageurl,
										$this->atts['title'],
										$html_size_attributes
									);

									if($link!=='0'&&$link!==''){
										$out[]='<a '.($this->atts['title']!=''?'title="'.$this->atts['title'].'" ':'').'href="'.$link.'" class="flickr">' .$image_tag. '</a>'
											.($this->atts['title']!=''?'<p class="wp-caption-text"><a href="'.$link.'">'.$this->atts['title'].'</a></p>':'');
									}else{
										$out[]= $image_tag.($this->atts['title']!=''?'<p class="wp-caption-text">'.$this->atts['title'].'</p>':'');
									}
								}
				*/
			}
		}
		if (!empty($out)) {
			$html = join(chr(10), $out);

			if ($this->atts['useDataSrc']) {
				$html = str_replace(' src="', ' data-original="', $html);
			}

			return sprintf(
				$this->image_wrapper_tag,
				$this->attr['size'],
				$html
			);
		} else {
			return '';
		}
	}

	private function view_map()
	{
		// coming in 2014
		// https://api.flickr.com/services/rest/?method=flickr.photos.search&format=json&nojsoncallback=1&radius=1&lat=46.67304&lon=7.70205&api_key=8703c9741dc1f3294c7e26c8b50eb5af&secret=203d87ac12749d97&user_id=87637435@N00
	}

	private function view_list()
	{
		$this->atts['limit'] = max($this->atts['per_page'], $this->atts['limit']);

		$transient_name = md5(($this->atts['tags']!=''?$this->atts['tags']:'_ALL').'-'.($this->atts['size']!=''?$this->atts['size']:'std').'.' .$this->atts['order']. '.' .$this->atts['limit']);

		if (false === ($html = get_transient($transient_name)) || $this->debug) {
			//	fetch data from flickr API
			$FlickrRequestString='https://api.flickr.com/services/rest/?method=flickr.photos.search&format=json&nojsoncallback=1&api_key='.$this->config['flickr_key'].'&secret='.$this->config['flickr_secret'].'&user_id='.$this->config['flickr_userid'].'&extras=o_dims,url_sq,url_t,url_s,url_q,url_m,url_n,url_z,url_c,url_l,url_o&tags='.urlencode($this->atts['tags']).'&per_page='.$this->atts['limit'].'&sort='.$this->atts['order'];

			$out=array();

			if (($image_data=$this->getRemoteFileContents($FlickrRequestString))) {
				$images = json_decode($image_data, true);
				if ($images['stat']=='ok') {
					$this->setMaxImageSize();

					$photoset=array();

					foreach ($images['photos']['photo'] as $photo) {
						if (isset($photo['id'])) {
							$photoset[] = $photo;
						}
					}

					foreach ($photoset as $photo) {
						$this->photo = $photo;
						$oembedhtml = $this->getFlickrHTMLViaOembed();

						if ($this->atts['useDataSrc']) {
							$oembedhtml = str_replace(' src="', ' data-original="', $oembedhtml);
						}

						$out[] = sprintf(
							$this->image_wrapper_tag,
							$this->attr['size'],
							$oembedhtml
						);
					}
				}
			}

			if (!empty($out)) {
				$html = '<div class="'.$this->key.'">'.
					'<div class="module row gallery images">'.
						$this->atts['text_pre'].join($this->atts['text_post'].chr(10).$this->atts['text_pre'], $out).$this->atts['text_post'].
					'</div>
				</div>';
	
				set_transient($transient_name, $html, DAY_IN_SECONDS);
			}
		}

		return $html;
	}

	private function setMaxImageSize()
	{
		if (isset($_COOKIE['resolution'])) {
			$parts = explode(',', $_COOKIE['resolution']);
			switch (count($parts)) {
				case 1:
					$this->screenSize = $parts[0]*.5;
					break;

				case 2:
					$this->screenSize = $parts[0]*$parts[1]*.5;
					break;
			}
			//$this->maxThumbSize = $this->screenSize*.5;
			$this->maxFullSize 	= $this->screenSize;
		}
	}

	private function getLargestImageForScreenSize()
	{
		$sizesRequestString='https://api.flickr.com/services/rest/?method=flickr.photos.getSizes&format=json&nojsoncallback=1&api_key='.$this->config['flickr_key'].'&secret='.$this->config['flickr_secret'].'&photo_id='.$this->atts['id'];
		$sizes = $this->getRemoteFileContents($sizesRequestString);
		$sizes = json_decode($sizes, true);
		$this->multisort($sizes['sizes']['size'], 'width', SORT_ASC);
		foreach ($sizes['sizes']['size'] as $key => $data) {
			if ($data['width']<$this->maxFullSize && $data['height']<$this->maxFullSize) {
				$this->imageurl = $data['source'];
			} else {
				break;
			}
		}
	}

	private function getLargestImage($id = 0)
	{
		$photo_id = $id ? $id : $this->atts['id'];
		$sizesRequestString='https://api.flickr.com/services/rest/?method=flickr.photos.getSizes&format=json&nojsoncallback=1&api_key='.$this->config['flickr_key'].'&secret='.$this->config['flickr_secret'].'&photo_id='.$photo_id;
		$sizes = $this->getRemoteFileContents($sizesRequestString);
		$sizes = json_decode($sizes, true);
		$this->multisort($sizes['sizes']['size'], 'width', SORT_DESC);
		$this->imageurl = $sizes['sizes']['size'][0]['source'];
		@file_put_contents($this->logfile, date('Y-m-d h:i:s').chr(9).'getLargestImage'.chr(9).$photo_id.PHP_EOL, FILE_APPEND);
	}


	private function backupLargestFlickrFile($photo_id)
	{
		$imageinfoString = 'https://api.flickr.com/services/rest/?method=flickr.photos.getInfo&format=json&nojsoncallback=1&api_key='.$this->config['flickr_key'].'&secret='.$this->config['flickr_secret'].'&photo_id='.$photo_id;
		$imageinfo = $this->getRemoteFileContents($imageinfoString);
		$imageinfo = json_decode($imageinfo, true);

		if ($imageinfo && isset($imageinfo['photo']) && isset($imageinfo['photo']['secret'])) {
			$sizesRequestString='https://api.flickr.com/services/rest/?method=flickr.photos.getSizes&extras=original_format&format=json&nojsoncallback=1&api_key='.$this->config['flickr_key'].'&secret='.$imageinfo['photo']['secret'].'&photo_id='.$photo_id;
			$sizes = $this->getRemoteFileContents($sizesRequestString);
			$sizes = json_decode($sizes, true);

			$this->multisortInt($sizes['sizes']['size'], 'width', SORT_DESC);
			$largestURL = $sizes['sizes']['size'][0]['source'];

			if (!file_exists($this->backupfolder . parse_url($largestURL, PHP_URL_PATH))) {
				$backupfile = parse_url($largestURL, PHP_URL_PATH);
				$urlbits = explode('/', $backupfile);
				if (!is_dir($this->backupfolder . '/' . $urlbits[1])) {
					@mkdir($this->backupfolder . '/' . $urlbits[1], 0755, true);
				}
				
				if (!is_dir($this->backupfolder . '/' . $urlbits[1])) {
					error_log(date('Y-m-d h:i:s').chr(9).$this->backupfolder . '/' . $backupfile[1] . chr(9) . 'Backup directory error' . PHP_EOL, 3, __DIR__ . '/log/backupLargestFlickrFile.log');
				} else {
					$destination = $this->backupfolder . parse_url($largestURL, PHP_URL_PATH);
					$success = @copy($largestURL, $destination);
					if ($success) {
						error_log(date('Y-m-d h:i:s') .chr(9). $destination .chr(9). 'File copied' . PHP_EOL, 3, __DIR__ . '/log/backupLargestFlickrFile.log');
					//return $this->backupfolderURL . parse_url($largestURL, PHP_URL_PATH);
					} else {
						error_log(date('Y-m-d h:i:s') .chr(9). $destination .chr(9). 'File not copied' . PHP_EOL, 3, __DIR__ . '/log/backupLargestFlickrFile.log');
						//return null;
					}
				}
			}
		}

		return null;
	}

	private function getFlickrHTMLViaOembed()
	{
		if (is_array($this->photo['owner'])) {
			$owner_id = $this->photo['owner']['path_alias'];
			if (!$owner_id) {
				$owner_id = $this->photo['owner']['nsid'];
			}
		} else {
			$owner_id = $this->photo['owner'];
		}

		$html = wp_oembed_get('https://www.flickr.com/photos/' .$owner_id. '/'.$this->photo['id'].'/', array('width' => $this->maxThumbSize));
		return $html;
	}

	private function javascriptWarning()
	{
		if ($this->flickrUserName!=='') {
			$linkToFlickr = ' Alternatively, you can see them <a href="//www.flickr.com/photos/' .$this->flickrUserName. '/tags/'.$this->atts['tags'].'/">in my Flickr photostream</a>.';
		} else {
			$linkToFlickr = '';
		}
		return '<noscript><p><em>In order to view the images in this gallery, you will need to activate JavaScript.' .$linkToFlickr. '</em></p></noscript>';
	}

	private function multisort(&$array, $key, $sortDirection = SORT_ASC)
	{
		/*
			via http://www.php.net/manual/en/function.usort.php#97750
			sort array $array by array key $key in sort direction $sortDirection
			sorts referenced array so no return necessary
			29.11.2011
		*/

		if (is_array($array) && (count($array)>0) && ((is_array($array[0]) && isset($array[0][$key])) || (is_object($array[0]) && isset($array[0]->$key)))) {
			usort($array, function ($a, $b) use ($key) {
				return strcmp($a[$key], $b[$key]);
			});
		}
	}

	private function multisortInt(&$array, $key, $sortDirection = SORT_ASC)
	{
		/*
			same as multisort but integer comparison
			11.11.2018
		*/

		if (is_array($array) && (count($array)>0) && ((is_array($array[0]) && isset($array[0][$key])) || (is_object($array[0]) && isset($array[0]->$key)))) {
			usort($array, function ($a, $b) use ($key, $sortDirection) {
				return $sortDirection == SORT_DESC ? (int)$a[$key] < (int)$b[$key] : (int)$a[$key] > (int)$b[$key];
			});
		}
	}
}
