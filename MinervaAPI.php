<?php
/**
 *	This class enables users to connect to Minerva and get a variety of info
 * 	Based on https://github.com/FelixVanderJeugt/minerva-syncer/blob/master/script.sh
 */
class Minerva
{
	public $urls=array(	"login"				=>	"https://minerva.ugent.be/secure/index.php?external=true",
						"home"				=>	"https://minerva.ugent.be/index.php",
						"profile"			=>	"https://minerva.ugent.be/main/auth/profile.php",
						"baseUrl"			=>	"https://minerva.ugent.be/",
						"documents"			=>	"https://minerva.ugent.be/main/document/document.php?cidReq=",
						"documentsBaseUrl"	=>	"https://minerva.ugent.be/main/document/",
						);
	
	public $username;
	public $auth=false;
	public $inited=false;
	public $ckfile;
	
	//cached files
	public $courses;
	public $id;
	public $fname;
	public $lname;
	public $email;
	public $lang;
	
	/**
	 *	Return Minerva object, call this as static
	 */
	public static function login($username, $password) {
		
		$minerva=new Minerva();
		$minerva->username=$username;
		$minerva->ckfile=tempnam ("/cookies", "C_".$username);
		
		//get salt
		$salt=Minerva::getSalt();
		
		//auth
		$ch = curl_init($minerva->urls["login"]);
		curl_setopt($ch, CURLOPT_POSTFIELDS,  "login=$username&password=$password&authentication_salt=$salt&submitAuth=Log in");
		curl_setopt($ch, CURLOPT_COOKIEFILE, $minerva->ckfile); 
		curl_setopt($ch, CURLOPT_COOKIEJAR, $minerva->ckfile); 
		curl_setopt($ch, CURLOPT_HEADER, 0);
    	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$out=curl_exec($ch);
		curl_close ($ch);
		
		$minerva->auth=true;
		
		return $minerva;
	}
	
	/**
	 *	Show cookie content
	 */
	public function getCookie() {
		if(!file_exists($this->ckfile))
			return "";
		$fh = fopen($this->ckfile, 'r');
		$cookie = fread($fh, filesize($this->ckfile));
		fclose($fh);
		return $cookie;
	}
	
	/**
	 *	Gives back a salt to log in with
	 */
	public static function getSalt() {
		//retreive salt
		$ch = curl_init($minerva->urls["login"]);//<-- <input type="hidden" name="authentication_salt" value="a415d3dbcfef8b2f57e92e340a50b81f" />
		curl_setopt($ch, CURLOPT_HEADER, 0);
		//curl_setopt($ch, CURLOPT_COOKIEJAR, $minerva->ckfile);  //cookie not needed while getting salt
    	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$saltPage=curl_exec($ch);
		curl_close ($ch);
		
		$startMatch="<input type=\"hidden\" name=\"authentication_salt\" value=\"";
		$salt=substr($saltPage,strpos($saltPage,$startMatch)+strlen($startMatch),32);
		//echo $salt;
		return $salt;
	}
	
	/**
	 *	Initialise courses and main info (won't re-init if already initted, call reinit() for that)
	 */
	public function init() {
		if(!$this->auth)
			throw new Exception('User is not yet authenticated.');
		if(!$this->inited) {
			$this->fetchCourses();
			$this->fetchProfile();
		}
		$this->inited=true;
	}
	
	/**
	 *	Re-init
	 */
	public function reinit() {
		$this->inited=false;
		$this->init();
	}
	
	/**
	 *	Checks if the user is authenticated
	 */
	public function isAuth() {
		return $auth;
	}
	
	/**
	 *	Fetch the courses
	 */
	public function fetchCourses() {
		preg_match_all("/http:\/\/minerva.ugent.be\/main\/course_home\/course_home.php\?cidReq=[^\"]*\"\>[^<]*\</",$this->getPage($this->urls["home"]),$matches);
		$data=array();
		foreach ($matches[0] as $k=>$m) {
			$e=explode("\">",$m);
			$cid=explode("?cidReq=",$e[0]);
			$data[$k]=array("cid"=>$cid[1],"name"=>substr_replace($e[1],"",-1));
		}
		$this->courses=$data;
	}
	
	/**
	 *	Fetch the user info
	 */
	public function fetchProfile() {
		$page=$this->getPage($this->urls["profile"]);
		
		$e=explode("<input type=\"hidden\" name=\"official_code\" value=\"",$page);
		$e=explode("\" />",$e[1]);
		$this->id=$e[0];
		
		$e=explode("<input type=\"hidden\" name=\"email\" value=\"",$page);
		$e=explode("\" />",$e[1]);
		$this->email=$e[0];
		
		$e=explode("<input type=\"hidden\" name=\"firstname\" value=\"",$page);
		$e=explode("\" />",$e[1]);
		$this->fname=$e[0];
		
		$e=explode("<input type=\"hidden\" name=\"lastname\" value=\"",$page);
		$e=explode("\" />",$e[1]);
		$this->lname=$e[0];
		
		$e=explode("<option value=\"dutch\" selected=\"selected\">",$page);
		$e=explode("</option>",$e[1]);
		$this->lang=$e[0];
	}
	
	/**
	 *	Get user id
	 */
	public function getUserId() {
		if(!$this->inited)
			$this->init();
		return $this->id;
	}
	
	/**
	 *	Get username
	 */
	public function getUsername() {
		return $this->username;
	}
	
	/**
	 *	Get user first name
	 */
	public function getUserFirstName() {
		if(!$this->inited)
			$this->init();
		return $this->fname;
	}


	/**
	 *	Get user last name
	 */
	public function getUserLastName() {
		if(!$this->inited)
			$this->init();
		return $this->lname;
	}
	
	/**
	 *	Get user email
	 */
	public function getUserEmail() {
		if(!$this->inited)
			$this->init();
		return $this->email;
	}
	
	/**
	 *	Get user language
	 */
	public function getUserLanguage() {
		if(!$this->inited)
			$this->init();
		return $this->lang;
	}

	
	/**
	 *	Show the courses (array of array(cid,coursename))
	 */
	public function getCourses() {
		if(!$this->inited)
			$this->init();
		return $this->courses;
	}
	
	/**
	 *	Get the documents of a course
	 */
	public function getCourseDocuments($cid) {
		if(!$this->inited)
			$this->init();
		return $this->makeDocumentList($this->urls["documents"].$cid);
	}
	
	/**
	 *	Make a document list
	 */
	public function makeDocumentList($url) {
		if(!$this->inited)
			$this->init();
		preg_match("/\<table class=\"display\"\>(.*)<\/table>/msU",$this->getPage($url),$match);
		preg_match_all("/<tr class=\"[^\"]*\">(.*)<\/tr>/msU",$match[0],$matches);
		//print_r($matches);
		$data=array();
		foreach ($matches[1] as $k=>$m) {
			if($k!=0) {
				
				//getting values with big explosions!
				$lines=explode("\n",$m);
				
				$ns1=explode("<td><img src=\"../img/",$lines[1]);
				$ns2=explode(".gif\"",$ns1[1]);
				$type=$ns2[0];
				
				$n1=explode("<span><a href=\"",$lines[2]);
				$n2=explode("\" id=\"",$n1[1]);
				$link=$n2[0];
				$n3=explode("\">",$n2[1]);
				$id=$n3[0];
				$n4=explode("</a>",$n3[1]);
				$name=$n4[0];
				$n5=explode("<a href=\"",$n3[2]);
				$n6=explode("\"",$n5[1]);
				$download=$n6[0];
				
				$ns1=explode("<td style=\"text-align:center;\">",$lines[5]);
				$ns2=explode("</td>",$ns1[1]);
				$date=$ns2[0];
				
				$data[$k-1]=array(	"type"		=>	$type,
									"link"		=>	$this->urls["documentsBaseUrl"].$link,
									"id"		=>	$id,
									"name"		=>	$name,
									"download"	=>	$this->urls["baseUrl"].$download,
									"date"		=>	$date,
								);

				if($type=="directory") {
					//$data[$k-1]["subfolder"]=$this->makeDocumentList($this->urls["documentsBaseUrl"].$link);
				}
			}
		}
		return $data;
	}
	
	/**
	 *	Returns a page with the auth cookies
	 */
	public function getPage($url) {
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->ckfile); 
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->ckfile); 
		curl_setopt($ch, CURLOPT_HEADER, 0);
    	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$out=curl_exec($ch);
		curl_close ($ch);
		return $out;
	}
}
?>