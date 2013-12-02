<?php
/**
 * AdaptiveImages
 *
 * @copyright  2013
 * @author     Nursit
 * @licence    GNU/GPL3
 */



class AdaptiveImages {
	/**
	 * @var AdaptiveImages
	 */
	static protected $instance;

	/**
	 * @var boolean
	 */
	protected $NojsPngGifProgressiveRendering = false;

	/**
	 * @var string
	 */
	protected $LowsrcJpgBgColor = 'ffffff';


	/**
	 * @var int
	 */
	protected $LowsrcJpgQuality = 10;

	/**
	 * @var int
	 */
	protected $X10JpgQuality = 85;

	/**
	 * @var int
	 */
	protected $X15JpgQuality = 65;

	/**
	 * @var int
	 */
	protected $X20JpgQuality = 45;

	/**
	 * @var array
	 */
	protected $DefaultBkpts = array(160,320,480,640,960,1440);

	/**
	 * @var int
	 */
	protected $MaxWidth1x = 640;

	/**
	 * @var int
	 */
	protected $MinWidth1x = 320;

	/**
	 * @var int
	 */
	protected $MaxWidthMobileVersion = 320;

	/**
	 * @var int
	 */
	protected $OnDemandImages = false;


	/**
	 * @var int
	 */
	protected $AcceptedFormats = array('gif','png','jpeg','jpg');

	/**
	 * @var int
	 */
	protected $DirectoryDest = "local/adapt-img/";


	/**
	 * Constructor
	 */
	protected function __construct(){
	}

	/**
	 * get
	 * @param $property
	 * @return mixed
	 * @throws InvalidArgumentException
	 */
	public function __get($property){
		if(!property_exists($this,$property) OR $property=="instance") {
      throw new InvalidArgumentException("Property {$property} doesn't exist");
    }
		return $this->{$property};
	}

	/**
	 * set
	 * @param $property
	 * @param $value
	 * @return mixed
	 * @throws InvalidArgumentException
	 */
	public function __set($property, $value){
		if(!property_exists($this,$property) OR $property=="instance") {
      throw new InvalidArgumentException("Property {$property} doesn't exist");
    }
		if (in_array($property,array("NojsPngGifProgressiveRendering","OnDemandImages")) AND !is_bool($value)){
			throw new InvalidArgumentException("Property {$property} needs a bool value");
		}
		elseif ($property=="LowsrcJpgBgColor" AND !is_string($value)){
			throw new InvalidArgumentException("Property {$property} needs a string value");
		}
		elseif ($property=="DefaultBkpts" AND !is_array($value)){
			throw new InvalidArgumentException("Property {$property} needs an array value");
		}
		elseif (!is_int($value)){
			throw new InvalidArgumentException("Property {$property} needs an int value");
		}
		if ($property=="DefaultBkpts"){
			sort($value);
		}

		return ($this->{$property} = $value);
	}

	/**
	 * Disable cloning
	 */
	protected function __clone() {
	 trigger_error("Cannot clone a singleton class", E_USER_ERROR);
	}

	/**
	 * Retrieve the AdaptiveImages object
	 *
	 * @return AdaptiveImages
	 */
	static public function getInstance() {
	 if (!(self::$instance instanceof self)) {
	   self::$instance = new self;
	 }
	 return self::$instance;
	}


	/**
	 * Process the full HTML page :
	 *  - adapt <img> in the HTML
	 *  - collect all inline <style> and put in the <head>
	 *  - add necessary JS
	 *
	 * @param string $html
	 * @return string
	 */
	public function adaptHTMLPage($html){
		#spip_timer();
		$html = $this->adaptHTMLPart($html);
		if (strpos($html,"adapt-img-wrapper")!==false){
			// les styles communs a toutes les images responsive en cours de chargement
			$ins = "<style type='text/css'>"."img.adapt-img{opacity:0.70;max-width:100%;height:auto;}"
			."span.adapt-img-wrapper,span.adapt-img-wrapper:after{display:inline-block;max-width:100%;position:relative;-webkit-background-size:100% auto;background-size:100% auto;background-repeat:no-repeat;line-height:1px;}"
			."span.adapt-img-wrapper:after{position:absolute;top:0;left:0;right:0;bottom:0;content:\"\"}"
			."</style>\n";
			// le script qui estime si la rapidite de connexion et pose une class aislow sur <html> si connexion lente
			// et est appele post-chargement pour finir le rendu (rend les images enregistrables par clic-droit aussi)
			$async_style = "html img.adapt-img{opacity:0.01}html span.adapt-img-wrapper:after{display:none;}";
			$length = strlen($html)+2000; // ~2000 pour le JS qu'on va inserer
			$ins .= "<script type='text/javascript'>/*<![CDATA[*/"
				."function adaptImgFix(n){var i=window.getComputedStyle(n.parentNode).backgroundImage.replace(/\W?\)$/,'').replace(/^url\(\W?|/,'');n.src=(i&&i!='none'?i:n.src);}"
				."(function(){function hAC(c){(function(H){H.className=H.className+' '+c})(document.documentElement)}"
				// Android 2 media-queries bad support workaround
				// muliple rules = multiples downloads : put .android2 on <html>
				// use with simple css without media-queries and send compressive image
				."var android2 = (/android 2[.]/i.test(navigator.userAgent.toLowerCase()));"
				."if (android2) {hAC('android2');}\n"
				// slowConnection detection
				."var slowConnection = false;"
				."if (typeof window.performance!==\"undefined\"){"
				."var perfData = window.performance.timing;"
				."var speed = ~~($length/(perfData.responseEnd - perfData.connectStart));" // approx, *1000/1024 to be exact
				//."console.log(speed);"
				."slowConnection = (speed && speed<50);" // speed n'est pas seulement une bande passante car prend en compte la latence de connexion initiale
				."}else{"
				//https://github.com/Modernizr/Modernizr/blob/master/feature-detects/network/connection.js
				."var connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;"
				."if (typeof connection!==\"undefined\") slowConnection = (connection.type == 3 || connection.type == 4 || /^[23]g$/.test(connection.type));"
				."}"
				//."console.log(slowConnection);"
				."if(slowConnection) {hAC('aislow');}\n"
				// injecter un style async apres chargement des images
			  // pour masquer les couches superieures (fallback et chargement)
				."var adaptImg_onload = function(){"
			  ."var sa = document.createElement('style'); sa.type = 'text/css';"
			  ."sa.innerHTML = '$async_style';"
			  ."var s = document.getElementsByTagName('style')[0]; s.parentNode.insertBefore(sa, s);};"
				// http://www.webreference.com/programming/javascript/onloads/index.html
				."function addLoadEvent(func){var oldol=window.onload;if (typeof oldol != 'function'){window.onload=func;}else{window.onload=function(){if (oldol){oldol();} func();}}}"
				."if (typeof jQuery!=='undefined') jQuery(function(){jQuery(window).load(adaptImg_onload)}); else addLoadEvent(adaptImg_onload);"
			  ."})();/*]]>*/</script>\n";
			// le noscript alternatif si pas de js (pour desactiver le rendu progressif qui ne rend pas bien les PNG transparents)
			if (!$this->NojsPngGifProgressiveRendering)
				$ins .= "<noscript><style type='text/css'>.png img.adapt-img,.gif img.adapt-img{opacity:0.01}span.adapt-img-wrapper.png:after,span.adapt-img-wrapper.gif:after{display:none;}</style></noscript>";
			// inserer avant le premier <script> ou <link a defaut

			// regrouper tous les styles adapt-img dans le head
			preg_match_all(",<!--\[if !IE\]><!-->.*(<style[^>]*>.*</style>).*<!--<!\[endif\]-->,Ums",$html,$matches);
			if (count($matches[1])){
				$html = str_replace($matches[1],"",$html);
				$ins .= implode("\n",$matches[1]);
			}
			if ($p = strpos($html,"<link") OR $p = strpos($html,"<script") OR $p = strpos($html,"</head"))
				$html = substr_replace($html,"<!--[if !IE]-->$ins\n<!--[endif]-->\n",$p,0);
		}
		#var_dump(spip_timer());
		return $html;
	}


	/**
	 * Adapt each <img> from HTML part
	 *
	 * @param string $html
	 * @param null $max_width_1x
	 * @return string
	 */
	public function adaptHTMLPart($html,$max_width_1x=null){
		static $bkpts = array();
		if (!is_null($max_width_1x))
			$max_width_1x = $this->MaxWidth1x;

		if ($max_width_1x AND !isset($bkpts[$max_width_1x])){
			$b = $this->$DefaultBkpts;
			while (count($b) AND end($b)>$max_width_1x) array_pop($b);
			// la largeur maxi affichee
			if (!count($b) OR end($b)<$max_width_1x) $b[] = $max_width_1x;
			$bkpts[$max_width_1x] = $b;
		}
		$bkpt = (isset($bkpts[$max_width_1x])?$bkpts[$max_width_1x]:null);

		$replace = array();
		preg_match_all(",<img\s[^>]*>,Uims",$html,$matches,PREG_SET_ORDER);
		if (count($matches)){
			foreach($matches as $m){
				$ri = $this->processImgTag($m[0], $bkpt, $max_width_1x);
				if ($ri!==$m[0]){
					$replace[$m[0]] = $ri;
				}
			}
			if (count($replace)){
				$html = str_replace(array_keys($replace),array_values($replace),$html);
			}
		}

		return $html;
	}



	/**
	 * ?action=adapt_img
	 * OnDemand production and delivery of BkptImage from it's URL
	 * strong path
	 *   local/adapt-img/w/x/file
	 *   ex : 320/20x/file
	 *   w est la largeur affichee de l'image
	 *   x est la resolution (10x => 1, 15x => 1.5, 20x => 2)
	 *   file le chemin vers le fichier source
	 */
	public function deliverBkptImage($path){

		$file = adaptive_images_bkpt_image_from_path($path, $mime);
		if (!$file
		  OR !$mime){
			http_status(404);
			throw new InvalidArgumentException("unable to find {$path} image");
		}

		header("Content-Type: ". $mime);
		#header("Expires: 3600"); // set expiration time

		if ($cl = filesize($file))
			header("Content-Length: ". $cl);

		readfile($file);
	}


	/**
	 * Process an image for a resolution breakpoint
	 *
	 * @param string $src
	 *   source image
	 * @param int $wkpt
	 *   breakpoint width (display width) for which the image is built
	 * @param int $wx
	 *   real width in px of image
	 * @param string $x
	 *   resolution 10x 15x 20x
	 * @param string $extension
	 *   extension
	 * @param bool $force
	 *   true to force immediate image building if not existing or if too old
	 * @return string
	 *   name of image file
	 */
	protected function processBkptImage($src, $wkpt, $wx, $x, $extension, $force=false){
		$dest = $this->DirectoryDest."$wkpt/$x/$src";
		if (($exist=file_exists($dest)) AND filemtime($dest)>=filemtime($src))
			return $dest;

		$force = ($force?true:!$this->OnDemandImages);

		// si le fichier existe mais trop vieux et que l'on ne veut pas le produire immediatement : supprimer le vieux fichier
		// ainsi le hit passera par la regexp et tommbera sur l'action adapt_img qui le produira
		if ($exist AND !$force)
			@unlink($dest);

		if (!$force)
			return $dest;

		// creer l'arbo
		$dirs = explode("/",$dest);
		$d = "";
		while(count($dirs)>1
			AND (
			  is_dir($f="$d/".($sd=array_shift($dirs)))
			  OR
			  $f = $this->mkDir($d,$sd)
			)
		) $d = $f;

		switch($x){
			case '10x':
				$quality = $this->X10JpgQuality;
				break;
			case '15x':
				$quality = $this->X15JpgQuality;
				break;
			case '20x':
				$quality = $this->X20JpgQuality;
				break;
		}

		$i = $this->imgSharpResize($src,$wx,10000,$quality);
		@copy($i,$dest);

		return file_exists($dest)?$dest:$src;
	}


	/**
	 * Produire une image d'apres son URL
	 * utilise par ?action=adapt_img pour la premiere production a la volee
	 * ou depuis adaptive_images_process_img() si on a besoin de l'image tout de suite
	 *
	 * @param string $arg
	 * @param string $mime
	 * @return string
	 */
	protected function processBkptImageFromPath($arg,&$mime){
		$base = _DIR_VAR."adapt-img/";
		if (strncmp($arg,$base,strlen($base))==0)
			$arg = substr($arg,strlen($base));

		$arg = explode("/",$arg);
		$wkpt = intval(array_shift($arg));
		$x = array_shift($arg);
		$src = implode("/",$arg);

		$parts = pathinfo($src);
		$extension = strtolower($parts['extension']);
		$mime = $this->extensionToMimeType($extension);
		$dpi = array('10x'=>1,'15x'=>1.5,'20x'=>2);

		if (!$wkpt
		  OR !isset($dpi[$x])
		  OR !file_exists($src)
		  OR !$mime){
			return "";
		}
		$wx = intval(round($wkpt * $dpi[$x]));

		$file = $this->processBkptImage($src, $wkpt, $wx, $x, $extension, true);
		return $file;
	}

	/**
	 * extrait les infos d'une image,
	 * calcule les variantes en fonction des breakpoints
	 * si l'image est de taille superieure au plus petit breakpoint
	 * et renvoi un markup responsive si il y a lieu
	 *
	 * @param string $img
	 * @param array $bkpt
	 * @param int $max_width_1x
	 * @return string
	 */
	protected function processImgTag($img, $bkpt, $max_width_1x){
		if (!$img) return $img;
		if (strpos($img, "adapt-img")!==false)
			return $img;
		if (is_null($bkpt) OR !is_array($bkpt))
			$bkpt = $this->$DefaultBkpts;

		list($h, $w) = $this->imgSize($img);
		if (!$w OR $w<=$this->MinWidth1x) return $img;

		$src = trim($this->tagAttribute($img, 'src'));
		if (strlen($src)<1){
			$src = $img;
			$img = "<img src='".$src."' />";
		}
		$src_mobile = $this->tagAttribute($img, 'data-src-mobile');

		// on ne touche pas aux data:uri
		if (strncmp($src, "data:", 5)==0)
			return $img;

		$images = array();
		if ($w<end($bkpt))
			$images[$w] = array(
				'10x' => $src,
				'15x' => $src,
				'20x' => $src,
			);
		$src = preg_replace(',[?][0-9]+$,', '', $src);

		// si on arrive pas a le lire, on ne fait rien
		if (!file_exists($src))
			return $img;

		$parts = pathinfo($src);
		$extension = $parts['extension'];

		// on ne touche pas aux GIF animes !
		if ($extension=="gif" AND $this->isAnimatedGif($src))
			return $img;

		// calculer les variantes d'image sur les breakpoints
		$fallback = $src;
		$wfallback = $w;
		$dpi = array('10x' => 1, '15x' => 1.5, '20x' => 2);
		$wk = 0;
		foreach ($bkpt as $wk){
			if ($wk>$w) break;
			$is_mobile = (($src_mobile AND $wk<=$this->MaxWidthMobileVersion) ? true : false);
			foreach ($dpi as $k => $x){
				$wkx = intval(round($wk*$x));
				if ($wkx>$w)
					$images[$wk][$k] = $src;
				else {
					$images[$wk][$k] = $this->processBkptImage($is_mobile ? $src_mobile : $src, $wk, $wkx, $k, $extension);
				}
			}
			if ($wk<=$max_width_1x AND ($is_mobile OR !$src_mobile)){
				$fallback = $images[$wk]['10x'];
				$wfallback = $wk;
			}
		}

		// Build the fallback img : High-compress JPG
		// Start from the larger or the mobile version if available
		if ($wk>$w && $w<$max_width_1x){
			$fallback = $images[$w]['10x'];
			$wfallback = $w;
		}

		// l'image n'a peut etre pas ete produite car _ADAPTIVE_IMAGES_ON_DEMAND_PRODUCTION = true
		// on la genere immediatement car on en a besoin
		if (!file_exists($fallback)){
			$mime = "";
			$this->processBkptImageFromPath($fallback, $mime);
		}
		// la qualite est reduite si la taille de l'image augmente, pour limiter le poids de l'image
		// regle de 3 au feeling, _ADAPTIVE_IMAGES_LOWSRC_JPG_QUALITY correspond a une image de 450kPx
		// et on varie dans +/-50% de _ADAPTIVE_IMAGES_LOWSRC_JPG_QUALITY
		$q = round($this->LowsrcJpgQuality-((min($max_width_1x, $wfallback)*$h/$w*min($max_width_1x, $wfallback))/75000-6));
		$q = min($q, round($this->LowsrcJpgQuality)*1.5);
		$q = max($q, round($this->LowsrcJpgQuality)*0.5);
		$fallback = $this->image_aplatir($fallback, 'jpg', $this->LowsrcJpgBgColor, $q);
		$images["fallback"] = $this->tagAttribute($fallback, "src");

		// l'image est reduite a la taille maxi (version IE)
		$src = $this->processBkptImage($src,$max_width_1x,$max_width_1x,'10x',$extension);
		list($h,$w) = $this->imgSize($src);
		$img = $this->setTagAttribute($img,"src",$src);
		$img = $this->setTagAttribute($img,"width",$w);
		$img = $this->setTagAttribute($img,"height",$h);

		// generer le markup
		return $this->imgAdaptiveMarkup($img, $images, $w, $h, $extension, $max_width_1x);
	}


	/**
	 *
	 * @param string $img
	 * @param array $rwd_images
	 *   tableau
	 *     width => file
	 * @param int $width
	 * @param int $height
	 * @param string $extension
	 * @param int $max_width_1x
	 * @return string
	 */
	function imgAdaptiveMarkup($img, $rwd_images, $width, $height, $extension, $max_width_1x){
		$class = $this->tagAttribute($img,"class");
		if (strpos($class,"adapt-img")!==false) return $img;
		ksort($rwd_images);
		$cid = "c".crc32(serialize($rwd_images));
		$style = "";
		if ($class) $class = " $class";
		$class = "$cid$class";
		$img = $this->setTagAttribute($img,"class","adapt-img-ie $class");

		// image de fallback fournie ?
		$fallback_file = "";
		if (isset($rwd_images['fallback'])){
			$fallback_file = $rwd_images['fallback'];
			unset($rwd_images['fallback']);
		}
		// sinon on affiche la plus petite image
		if (!$fallback_file){
			$fallback_file = reset($rwd_images);
			$fallback_file = $fallback_file['10x'];
		}
		// embarquer le fallback en DATA URI si moins de 32ko (eviter une page trop grosse)
		$fallback_file = $this->base64EmbedFile($fallback_file);

		$prev_width = 0;
		$medias = array();
		$lastw = array_keys($rwd_images);
		$lastw = end($lastw);
		$wandroid = 0;
		foreach ($rwd_images as $w=>$files){
			if ($w==$lastw) {$islast = true;}
			if ($w<=$this->MaxWidthMobileVersion) $wandroid = $w;
			// il faut utiliser une clause min-width and max-width pour que les regles soient exlusives
			if ($prev_width<$max_width_1x){
				$hasmax = (($islast OR $w>=$max_width_1x)?false:true);
				$mw = ($prev_width?"and (min-width:{$prev_width}px)":"").($hasmax?" and (max-width:{$w}px)":"");
				$htmlsel = "html:not(.android2)";
				$htmlsel = array(
					'10x' => "$htmlsel",
					'15x' => "$htmlsel:not(.aislow)",
					'20x' => "$htmlsel:not(.aislow)",
				);
			}
			$mwdpi = array(
				'10x' => "screen $mw",
				'15x' => "screen and (-webkit-min-device-pixel-ratio: 1.5) and (-webkit-max-device-pixel-ratio: 1.99) $mw,screen and (min--moz-device-pixel-ratio: 1.5) and (max--moz-device-pixel-ratio: 1.99) $mw",
				'20x' => "screen and (-webkit-min-device-pixel-ratio: 2) $mw,screen and (min--moz-device-pixel-ratio: 2) $mw",
			);
			foreach($files as $kx=>$file){
				if (isset($mwdpi[$kx])){
					// $file = "filedelay.api/5/$file"; // debug : injecter une tempo dans le chargement de l'image pour tester l'enrichissement progressif
					//$file = $file."?rwd"; // debug  : etre sur qu'on charge bien l'image issue des medias queries
					$mw = $mwdpi[$kx];
					$not = $htmlsel[$kx];
					$medias[$mw] = "@media $mw{{$not} span.$cid,{$not} span.$cid:after{background-image:url($file);}}";
				}
			}
			$prev_width = $w+1;
		}

		// Une regle CSS simple pour android qui (selon les versions/nav) n'arrive pas a s'y retrouver dans les media-queries
		// et charge toutes les images
		// donc une seule image, JPG 320 - 1.5x (compromis)
		if ($wandroid){
			$file = $rwd_images[$wandroid]['15x'];
			$medias['android2'] = "html.android2 span.$cid,html.android2 span.$cid:after{background-image:url($file);}";
		}

		// Media Queries
		$style .= implode("",$medias);

		$out = "<!--[if IE]>$img<![endif]-->\n";
		$img = $this->setTagAttribute($img,"src",$fallback_file);
		$img = $this->setTagAttribute($img,"class","adapt-img $class");
		$img = $this->setTagAttribute($img,"onmousedown","adaptImgFix(this)");
		// $img = setTagAttribute($img,"onkeydown","adaptImgFix(this)"); // usefull ?
		$out .= "<!--[if !IE]><!--><span class=\"adapt-img-wrapper $cid $extension\">$img</span>\n<style>$style</style><!--<![endif]-->";

		return $out;
	}



	/**
	 * Get height and width from an image file or <img> tag
	 * @param string $img
	 * @return array
	 *  (height,width)
	 */
	protected function imgSize($img) {

		static $largeur_img =array(), $hauteur_img= array();
		$srcWidth = 0;
		$srcHeight = 0;

		$logo = $this->tagAttribute($img,'src');

		if (!$logo) $logo = $img;
		else {
			$srcWidth = $this->tagAttribute($img,'width');
			$srcHeight = $this->tagAttribute($img,'height');
		}

		// never process on remote img
		if (preg_match(';^(\w{3,7}://);', $logo)){
			return array(0,0);
		}
		// remove timestamp on URL
		if (($p=strpos($logo,'?'))!==FALSE)
			$logo=substr($logo,0,$p);

		$srcsize = false;
		if (isset($largeur_img[$logo]))
			$srcWidth = $largeur_img[$logo];
		if (isset($hauteur_img[$logo]))
			$srcHeight = $hauteur_img[$logo];
		if (!$srcWidth OR !$srcHeight){
			if (file_exists($logo)
				AND $srcsize = @getimagesize($logo)){
				if (!$srcWidth)	$largeur_img[$logo] = $srcWidth = $srcsize[0];
				if (!$srcHeight)	$hauteur_img[$logo] = $srcHeight = $srcsize[1];
			}
		}
		return array($srcHeight, $srcWidth);
	}


	/**
	 * recuperer un attribut d'une balise html
	 * la regexp est mortelle : cf. tests/filtres/tagAttribute.php
	 * Si on a passe un tableau de balises, renvoyer un tableau de resultats
	 * (dans ce cas l'option $complet n'est pas disponible)
	 * @param $balise
	 * @param $attribut
	 * @param $complet
	 * @return array|null|string
	 */
	protected function tagAttribute($balise, $attribut, $complet = false) {
		if (preg_match(
		',(^.*?<(?:(?>\s*)(?>[\w:.-]+)(?>(?:=(?:"[^"]*"|\'[^\']*\'|[^\'"]\S*))?))*?)(\s+'
		.$attribut
		.'(?:=\s*("[^"]*"|\'[^\']*\'|[^\'"]\S*))?)()([^>]*>.*),isS',

		$balise, $r)) {
			if ($r[3][0] == '"' || $r[3][0] == "'") {
				$r[4] = substr($r[3], 1, -1);
				$r[3] = $r[3][0];
			} elseif ($r[3]!=='') {
				$r[4] = $r[3];
				$r[3] = '';
			} else {
				$r[4] = trim($r[2]);
			}
			$att = str_replace("&#39;", "'", $r[4]);
		}
		else
			$att = NULL;

		if ($complet)
			return array($att, $r);
		else
			return $att;
	}


	/**
	 * modifier (ou inserer) un attribut html dans une balise
	 *
	 * http://doc.spip.org/@setTagAttribute
	 *
	 * @param string $balise
	 * @param string $attribut
	 * @param string $val
	 * @param bool $proteger
	 * @param bool $vider
	 * @return string
	 */
	protected function setTagAttribute($balise, $attribut, $val, $proteger=true, $vider=false) {
		// preparer l'attribut
		// supprimer les &nbsp; etc mais pas les balises html
		// qui ont un sens dans un attribut value d'un input
		if ($proteger) {
			$val = preg_replace(array(",\n,",",\s(?=\s),msS"),array(" ",""),strip_tags($val));
			$val = str_replace(array("'",'"'),array('&#039;', '&#034;'), $val);
		}

		// echapper les ' pour eviter tout bug
		$val = str_replace("'", "&#039;", $val);
		if ($vider AND strlen($val)==0)
			$insert = '';
		else
			$insert = " $attribut='$val'";

		list($old, $r) = $this->tagAttribute($balise, $attribut, true);

		if ($old !== NULL) {
			// Remplacer l'ancien attribut du meme nom
			$balise = $r[1].$insert.$r[5];
		}
		else {
			// preferer une balise " />" (comme <img />)
			if (preg_match(',/>,', $balise))
				$balise = preg_replace(",\s?/>,S", $insert." />", $balise, 1);
			// sinon une balise <a ...> ... </a>
			else
				$balise = preg_replace(",\s?>,S", $insert.">", $balise, 1);
		}

		return $balise;
	}


	/**
	 * Mkdir $base/${subdir}/
	 *
	 * @param $base
	 * @param string $subdir
	 * @param bool $nobase
	 * @param bool $tantpis
	 * @return string
	 * @throws Exception
	 */
	protected function mkDir($base, $subdir='', $nobase = false, $tantpis=false) {
		static $dirs = array();

		$base = str_replace("//", "/", $base);

		# suppr le dernier caractere si c'est un / ou un _
		$base = rtrim($base, '/_');

		if (!strlen($subdir)) {
			$n = strrpos($base, "/");
			if ($n === false) return $nobase ? '' : ($base .'/');
			$subdir = substr($base, $n+1);
			$base = substr($base, 0, $n+1);
		} else {
			$base .= '/';
			$subdir = str_replace("/", "", $subdir);
		}

		$baseaff = $nobase ? '' : $base;
		if (isset($dirs[$base.$subdir]))
			return $baseaff.$dirs[$base.$subdir];

		$path = $base.$subdir; # $path = 'IMG/distant/pdf' ou 'IMG/distant_pdf'

		if (!@is_dir("$path/") AND !@mkdir($path)){
			throw new Exception("Unable to mkdir {$path}");
		}

		return $baseaff.($dirs[$base.$subdir] = "$subdir/");
	}

	/**
	 * Provide Mime Type for Image file Extension
	 * @param $extension
	 * @return string
	 */
	protected function extensionToMimeType($extension){
		static $MimeTable = array(
			'jpg' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png' => 'image/png',
			'gif' => 'image/gif',
		);

		return (isset($MimeTable[$extension])?$MimeTable[$extension]:'image/jpeg');
	}


	/**
	 * Detect animated GIF : don't touch it
	 * http://it.php.net/manual/en/function.imagecreatefromgif.php#59787
	 *
	 * @param string $filename
	 * @return bool
	 */
	protected function isAnimatedGif($filename){
		$filecontents = file_get_contents($filename);

		$str_loc = 0;
		$count = 0;
		while ($count<2) # There is no point in continuing after we find a 2nd frame
		{

			$where1 = strpos($filecontents, "\x00\x21\xF9\x04", $str_loc);
			if ($where1===FALSE){
				break;
			} else {
				$str_loc = $where1+1;
				$where2 = strpos($filecontents, "\x00\x2C", $str_loc);
				if ($where2===FALSE){
					break;
				} else {
					if ($where1+8==$where2){
						$count++;
					}
					$str_loc = $where2+1;
				}
			}
		}

		if ($count>1){
			return (true);

		} else {
			return (false);
		}
	}

	/**
	 * Embed image file in Base 64 URI
	 *
	 * @param string $filename
	 * @param int $maxsize
	 * @return string
	 *     URI Scheme of base64 if possible,
	 *     or URL from source file
	 */
	function base64EmbedFile ($filename, $maxsize = 32768) {
		$extension = substr(strrchr($filename,'.'),1);

		if (!file_exists($filename)
			OR filesize($filename)>$maxsize
			OR !$content = file_get_contents($filename))
			return $filename;

		$base64 = base64_encode($content);
		$encoded = 'data:'.$this->extensionToMimeType($extension).';base64,'.$base64;

		return $encoded;
	}

	protected function image_aplatir(){

	}


	function imgSharpResize($source, $taille = 0, $taille_y = 0, $qualite=null){
		// ordre de preference des formats graphiques pour creer les vignettes
		// le premier format disponible, selon la methode demandee, est utilise

		$valeurs = $this->_image_valeurs_trans($source, "reduire_net-{$taille}-{$taille_y}-{$qualite}", false);
		if (!$valeurs) return $source;

		if ($taille==0 AND $taille_y>0)
			$taille = 10000; # {0,300} -> c'est 300 qui compte
		elseif ($taille>0 AND $taille_y==0)
			$taille_y = 10000; # {300,0} -> c'est 300 qui compte
		elseif ($taille==0 AND $taille_y==0)
			return $source;

		$image = $valeurs['fichier'];
		$format = $valeurs['format_source'];

		$destdir = dirname($valeurs['fichier_dest']);
		$destfile = basename($valeurs['fichier_dest'], "." . $valeurs["format_dest"]);

		$format_sortie = $valeurs['format_dest'];

		$destination = "$destdir/$destfile";

		// calculer la taille
		$srcWidth = $valeurs['largeur'];
		$srcHeight = $valeurs['hauteur'];
		list($destWidth,$destHeight) = $this->_image_ratio($srcWidth, $srcHeight, $taille, $taille_y);

		if ($image['creer']==false)
			return $image['fichier_dest'];

		// Si l'image est de la taille demandee (ou plus petite), simplement
		// la retourner
		if ($srcWidth
		  AND $srcWidth<=$destWidth
		  AND $srcHeight<=$destHeight){

			$valeurs['format_dest'] = $format;
			$valeurs['fichier_dest'] = $destination.".".$format;
			@copy($image, $valeurs['fichier_dest']);

		}
		else {
			if (defined('_IMG_GD_MAX_PIXELS') AND $srcWidth*$srcHeight>_IMG_GD_MAX_PIXELS){
				spip_log("vignette gd1/gd2 impossible : " . $srcWidth*$srcHeight . "pixels");
				return $image;
			}
			$destFormat = $format_sortie;
			if (!$destFormat){
				spip_log("pas de format pour $image");
				return $image;
			}

			$fonction_imagecreatefrom = $valeurs['fonction_imagecreatefrom'];
			if (!function_exists($fonction_imagecreatefrom))
				return $image;
			$srcImage = @$fonction_imagecreatefrom($image);
			if (!$srcImage){
				spip_log("echec gd1/gd2");
				return $image;
			}

			// Initialisation de l'image destination
			if ($destFormat!="gif")
				$destImage = ImageCreateTrueColor($destWidth, $destHeight);
			if (!$destImage)
				$destImage = ImageCreate($destWidth, $destHeight);

			// Recopie de l'image d'origine avec adaptation de la taille
			$ok = false;
			if (function_exists('ImageCopyResampled')){
				if ($format=="gif"){
					// Si un GIF est transparent,
					// fabriquer un PNG transparent
					$transp = imagecolortransparent($srcImage);
					if ($transp>0) $destFormat = "png";
				}
				if ($destFormat=="png"){
					// Conserver la transparence
					if (function_exists("imageAntiAlias")) imageAntiAlias($destImage, true);
					@imagealphablending($destImage, false);
					@imagesavealpha($destImage, true);
				}
				$ok = @ImageCopyResampled($destImage, $srcImage, 0, 0, 0, 0, $destWidth, $destHeight, $srcWidth, $srcHeight);
			}
			if (!$ok)
				$ok = ImageCopyResized($destImage, $srcImage, 0, 0, 0, 0, $destWidth, $destHeight, $srcWidth, $srcHeight);

			if ($destFormat=="jpg" && function_exists('imageconvolution')){
				$intSharpness = _findSharp($srcWidth, $destWidth);
				$arrMatrix = array(
					array(-1, -2, -1),
					array(-2, $intSharpness+12, -2),
					array(-1, -2, -1)
				);
				imageconvolution($destImage, $arrMatrix, $intSharpness, 0);
			}
			// Sauvegarde de l'image destination
			$valeurs['fichier_dest'] = "$destination.$destFormat";
			$valeurs['format_dest'] = $format = $destFormat;

			$this->_image_gd_output($destImage, $valeurs, $qualite);

			if ($srcImage)
				ImageDestroy($srcImage);
			ImageDestroy($destImage);
		}

		return $valeurs['fichier_dest'];

	}


	/**
	 * Fonctions de traitement d'image
	 * Uniquement pour GD2.
	 *
	 * @param string $img
	 * 		Un tag html <img src=... />.
	 * @param string $effet
	 * 		Les nom et param�tres de l'effet � apporter sur l'image
	 * 		(par exemple : reduire-300-200).
	 * @param bool|string $forcer_format
	 * 		Un nom d'extension sp�cifique demand� (par exemple : jpg, png, txt...
	 * 		par d�faut false : GD se d�brouille seule).
	 * @param array $fonction_creation
	 * 		Un tableau � 2 �l�ments. Le premier (string) indique le nom du
	 * 		filtre de traitement demand� (par exemple : image_reduire) ; le
	 * 		second (array) est lui-m�me un tableau reprenant la valeur de $img
	 * 		et chacun des param�tres pass�s au filtre.
	 * @return bool|string|array
	 * 		false si pas de tag <img,
	 * 		    si l'extension n'existe pas,
	 * 		    si le fichier source n'existe pas,
	 * 		    si les dimensions de la source ne sont pas accessibles,
	 * 		    si le fichier temporaire n'existe pas,
	 * 		    si la fonction _imagecreatefrom{extension} n'existe pas ;
	 * 		"" (cha�ne vide) si le fichier source est distant et n'a pas
	 * 		    r�ussi � �tre copi� sur le serveur ;
	 * 		l'appel � la fonction pipeline image_preparer_filtre.
	 */
	protected function _image_valeurs_trans($img, $effet, $forcer_format = false, $fonction_creation = NULL) {
		static $images_recalcul = array();
		if (strlen($img)==0) return false;

		$source = trim($this->tagAttribute($img, 'src'));
		if (strlen($source) < 1){
			$source = $img;
			$img = "<img src='$source' />";
		}
		# gerer img src="data:....base64"
		else if (preg_match('@^data:image/(jpe?g|png|gif);base64,(.*)$@isS', $source, $regs)) {
			return false;
		}

		// les protocoles web prennent au moins 3 lettres
		if (preg_match(';^(\w{3,7}://);', $source)){
			return false;
		}	else {
			// enlever le timestamp eventuel
			$source=preg_replace(',[?][0-9]+$,','',$source);
			$fichier = $source;
		}

		$terminaison_dest = "";
		if (preg_match(",\.(gif|jpe?g|png)($|[?]),i", $fichier, $regs)) {
			$terminaison = strtolower($regs[1]);
			$terminaison_dest = $terminaison;

			if ($terminaison == "gif") $terminaison_dest = "png";
		}
		if ($forcer_format!==false) $terminaison_dest = $forcer_format;

		if (!$terminaison_dest) return false;

		$term_fonction = $terminaison;
		if ($term_fonction == "jpg") $term_fonction = "jpeg";

		$nom_fichier = substr($fichier, 0, strlen($fichier) - (strlen($terminaison) + 1));
		$fichier_dest = $nom_fichier;
		if (@file_exists($f = $fichier)){
			// on passe la balise img a taille image qui exraira les attributs si possible
			// au lieu de faire un acces disque sur le fichier
			list ($ret["hauteur"],$ret["largeur"]) = $this->imgSize($img);
			$date_src = @filemtime($f);
		}
		else
			return false;

		// pas de taille mesurable
		if (!($ret["hauteur"] OR $ret["largeur"]))
			return false;


		// cas general :
		// on a un dossier cache commun et un nom de fichier qui varie avec l'effet
		// cas particulier de reduire :
		// un cache par dimension, et le nom de fichier est conserve, suffixe par la dimension aussi
		$cache = "cache-gd2";
		if (substr($effet,0,7)=='reduire') {
			list(,$maxWidth,$maxHeight) = explode('-',$effet);
			list ($destWidth,$destHeight) = $this->_image_ratio($ret['largeur'], $ret['hauteur'], $maxWidth, $maxHeight);
			$ret['largeur_dest'] = $destWidth;
			$ret['hauteur_dest'] = $destHeight;
			$effet = "L{$destWidth}xH$destHeight";
			$cache = "cache-vignettes";
			$fichier_dest = basename($fichier_dest);
			if (($ret['largeur']<=$maxWidth)&&($ret['hauteur']<=$maxHeight)){
				// on garde la terminaison initiale car image simplement copiee
				// et on postfixe son nom avec un md5 du path
				$terminaison_dest = $terminaison;
				$fichier_dest .= '-'.substr(md5("$fichier"),0,5);
			}
			else
				$fichier_dest .= '-'.substr(md5("$fichier-$effet"),0,5);
			$cache = sous_repertoire(_DIR_VAR, $cache);
			$cache = sous_repertoire($cache, $effet);
			# cherche un cache existant
			/*foreach (array('gif','jpg','png') as $fmt)
				if (@file_exists($cache . $fichier_dest . '.' . $fmt)) {
					$terminaison_dest = $fmt;
				}*/
		}
		else 	{
			$fichier_dest = md5("$fichier-$effet");
			$cache = sous_repertoire(_DIR_VAR, $cache);
		}

		$fichier_dest = $cache . $fichier_dest . "." .$terminaison_dest;

		$creer = true;
		if (@file_exists($f = $fichier_dest)){
			if (filemtime($f)>=$date_src)
				$creer = false;
		}

		$ret["fonction_imagecreatefrom"] = "imagecreatefrom".$term_fonction;
		$ret["fichier"] = $fichier;
		$ret["fichier_dest"] = $fichier_dest;
		$ret["format_source"] = ($terminaison != 'jpeg' ? $terminaison : 'jpg');
		$ret["format_dest"] = $terminaison_dest;
		$ret["date_src"] = $date_src;
		$ret["creer"] = $creer;
		$ret["tag"] = $img;

		if (!function_exists($ret["fonction_imagecreatefrom"])) return false;
		return $ret;
	}

	// Calculer le ratio
	// http://doc.spip.org/@image_ratio
	function _image_ratio($srcWidth, $srcHeight, $maxWidth, $maxHeight) {
		$ratioWidth = $srcWidth/$maxWidth;
		$ratioHeight = $srcHeight/$maxHeight;

		if ($ratioWidth <=1 AND $ratioHeight <=1) {
			$destWidth = $srcWidth;
			$destHeight = $srcHeight;
		} else if ($ratioWidth < $ratioHeight) {
			$destWidth = $srcWidth/$ratioHeight;
			$destHeight = $maxHeight;
		}
		else {
			$destWidth = $maxWidth;
			$destHeight = $srcHeight/$ratioWidth;
		}
		return array (ceil($destWidth), ceil($destHeight),
			max($ratioWidth,$ratioHeight));
	}

	/**
	 * Affiche ou sauvegarde une image au format PNG
	 * Utilise les fonctions sp�cifiques GD.
	 *
	 * @param ressource $img
	 * 		Une ressource de type Image GD.
	 * @param array $valeurs
	 * @param int $qualite
	 * @return bool
	 */
	protected function _image_gd_output($img, $valeurs, $qualite=null) {
		$fichier = $valeurs['fichier_dest'];
		$tmp = $fichier.".tmp";
		switch($valeurs['format_dest']){
			case "gif":
				$ret = imagepng($img,$tmp);
				break;
			case "png":
				$ret = imagepng($img,$tmp);
				break;
			case "jpg":
			case "jpeg":
				$ret = imagejpeg($img,$tmp,$qualite);
				break;
		}
		if(file_exists($tmp)){
			$taille_test = getimagesize($tmp);
			if ($taille_test[0] < 1) return false;

			@unlink($fichier); // le fichier peut deja exister
			@rename($tmp, $fichier);
			return $ret;
		}
		return false;
	}
}



?>
