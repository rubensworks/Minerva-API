<?php
/**
 *	This class enables users to connect to Minerva and get a variety of info
 * 	Based on https://github.com/FelixVanderJeugt/minerva-syncer/blob/master/script.sh
 * 	This class can also be used in single-request mode by first using auth()
 */
class Minerva
{
	public $urls=array(	"login"					=>	"https://minerva.ugent.be/secure/index.php?external=true",
						"home"					=>	"https://minerva.ugent.be/index.php",
						"profile"				=>	"https://minerva.ugent.be/main/auth/profile.php",
						"baseUrl"				=>	"https://minerva.ugent.be/",
						"courseHome"			=>	"https://minerva.ugent.be/main/course_home/course_home.php?gidReset=1&cidReq=",
						"documents"				=>	"https://minerva.ugent.be/main/document/document.php?cidReq=",
						"documentsSubdir"		=>	"&curdirpath=",
						"documentsBaseUrl"		=>	"https://minerva.ugent.be/main/document/",
						"announcements"			=>	"https://minerva.ugent.be/main/announcements/announcements.php?cidReq=",
						"announcementsPerPage"	=>	"&per_page=",
						"announcementsPageNr"	=>	"&page_nr=",
						);
	
	public $username;
	public $auth=false;
	public $inited=false;
	public $singlemode=false;
	public $ckfile;
	
	//cached files
	public $courses;
	public $id;
	public $fname;
	public $lname;
	public $email;
	public $lang;
	
	/**
	 *	Defines Minerva object by logging in
	 */
	public function login($username, $password) {
		
		//$this=new Minerva();
		$this->username=$username;
		$this->ckfile=tempnam ("/cookies", "C_".$username);
		
		//get salt
		$salt=Minerva::getSalt();
		
		//auth
		$ch = curl_init($this->urls["login"]);
		curl_setopt($ch, CURLOPT_POSTFIELDS,  "login=$username&password=$password&authentication_salt=".$salt["salt"]."&submitAuth=Log in");
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->ckfile); 
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->ckfile); 
		curl_setopt($ch, CURLOPT_HEADER, 0);
    	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$out=curl_exec($ch);
		curl_close ($ch);
		
		$this->auth=true;
		$this->singlemode=false;
		$this->fetchProfile();
		$this->auth=$this->fname!="";
		
		echo $this->getCookie();
		
		//return $minerva;
		//return $this->auth;
		return array("authenticated"=>$this->auth?1:0);
	}
	
	/**
	 *	Defines Minerva object by authenticating with the cookie data
	 */
	public function auth($username,$cookie) {
		
		//$this=new Minerva();
		//$minerva->username=$username;
		$this->username=$username;
		$this->ckfile=tempnam ("/cookies", "C_".$username);
		$this->setCookie("minerva.ugent.be	FALSE	/	FALSE	0	mnrv_sid	$cookie
minerva.ugent.be	FALSE	/	FALSE	0	mnrv_username	$username");
		
		$this->auth=true;
		$this->singlemode=true;
		$this->fetchProfile();
		$this->auth=$this->fname!="";
		
		return array("authenticated"=>$this->auth?1:0);
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
	 *	Set cookie content
	 */
	public function setCookie($value) {
		$fh = fopen($this->ckfile, 'w') or die("can't open file");
		fwrite($fh, $value);
	}
	
	/**
	 *	Gives back a salt to log in with
	 */
	public static function getSalt() {
		$minerva=new Minerva();
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
		return array("salt"=>$salt);
	}
	
	/**
	 *	Initialise courses and main info (won't re-init if already initted, call reinit() for that)
	 */
	public function init() {
		if(!$this->auth)
			throw new CHttpException('1000','Authentication failed.');
		if(!$this->inited && !$this->singlemode) {
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
			$data[$k]=array("course"=>array("cid"=>$cid[1],"name"=>substr_replace($e[1],"",-1)));
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
		return array("userId"=>$this->id);
	}
	
	/**
	 *	Get username
	 */
	public function getUsername() {
		return array("username"=>$this->username);
	}
	
	/**
	 *	Get user first name
	 */
	public function getUserFirstName() {
		if(!$this->inited)
			$this->init();
		return array("userFirstName"=>$this->fname);
	}


	/**
	 *	Get user last name
	 */
	public function getUserLastName() {
		if(!$this->inited)
			$this->init();
		return array("userLastName"=>$this->lname);
	}
	
	/**
	 *	Get user email
	 */
	public function getUserEmail() {
		if(!$this->inited)
			$this->init();
		return array("userEmail"=>$this->email);
	}
	
	/**
	 *	Get user language
	 */
	public function getUserLanguage() {
		if(!$this->inited)
			$this->init();
		return array("userLanguage"=>$this->lang);
	}

	
	/**
	 *	Show the courses (array of array(cid,coursename))
	 */
	public function getCourses() {
		if(!$this->inited)
			$this->init();
		if($this->singlemode)
			$this->fetchCourses();
		return array("courses"=>$this->courses);
	}
	
	/**
	 *	Get the tools of a course
	 */
	public function getTools($cid) {
		if(!$this->inited)
			$this->init();
		$c=$this->getPage($this->urls["courseHome"].$cid);
		preg_match("/\<div id=\"tools\">(.*)<div id=\"homepagelinks/msU",$c,$match);
		preg_match_all("/<div id=\"tool_(.*)<\/div>/msU",$match[0],$matches);
		
		$data=array();
		
		foreach ($matches[0] as $m) {
			
			$name=$this->getContent("<div id=\"tool_","\" class",$m);
			$enabled=$this->getContent("class=\"tool ","row",$m);
						
			$data[]=array("tool"=>array(
											"name"		=>$name,
											"enabled"	=>$enabled==""?1:0,
			));
		}
		
		$intro=$this->getContent("<div id=\"courseintro\">","</div>",$c);
		
		//also return course intro
		return array(	"intro"=>$intro,
						"tools"=>$data,
						);
	}
	
	/**
	 *	Get the documents of a course
	 */
	public function getCourseDocuments($cid,$subfolder="") {
		if(!$this->inited)
			$this->init();
		return array("documents"=>$this->makeDocumentList($this->urls["documents"].$cid.($subfolder==""?"":($this->urls["documentsSubdir"].$subfolder))));
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
				
				$n1[1]=preg_replace("/\s\s+/"," ",$n1[1]);
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
				
				$data[$k-1]=array("document"=>array(	"type"		=>	$type,
									"link"		=>	$this->urls["documentsBaseUrl"].htmlspecialchars($link),
									"id"		=>	$id,
									"name"		=>	$name,
									"download"	=>	$this->urls["baseUrl"].$download,
									"date"		=>	$date,
								));

				if($type=="directory") {
					//main/document/document.php?cidReq=E00862002012&amp;amp;curdirpath=%2F1_Theory
					$n1=explode("curdirpath=",$link);
					$subfolder=$n1[1];
					$data[$k-1]["document"]["subfolder"]=$subfolder;
				}
			}
		}
		return $data;
	}
	
	/**
	 *	Get the announcements of a course
	 */
	public function getAnnouncements($cid,$page=1,$perpage=20) {
		if(!$this->inited)
			$this->init();
		$c=$this->getPage($this->urls["announcements"].$cid.$this->urls["announcementsPageNr"].$page.$this->urls["announcementsPerPage"].$perpage);
		preg_match("/\<form action=\"announcements.php\?per_page=$perpage&page_nr=$page\" method=\"post\">(.*)<\/form>/msU",$c,$match);
		preg_match_all("/<div class=\"announcement \">(.*)<\/div><\/div>/msU",$match[0],$matches);
		
		$data=array();
		
		foreach ($matches[0] as $m) {
			$id=$this->getContent("name=\"id[]\" value=\"","\" class=\"announcement_actions\" onclick=\"unlockmultipleactions('id[]');\">",$m);
			$title=$this->getContent("\" class=\"announcement_actions\" onclick=\"unlockmultipleactions('id[]');\">","</div>",$m);
			
			$mailed=str_replace("<img src=\"http://minerva.ugent.be/main/img/email.png\" alt=\"Email\" title=\"Email\"   />","",$title);
			if($title==$mailed) {
				$title=$mailed;
				$mailed=false;
			}
			else $mailed=true;
			
			$visibility=$this->getContent(" <select name=\"sent to\"><option value=\"\">","</option></select>",$m);
			$created=$this->getContent("</option></select><span class=\"right invisible\">","<br /></span></div><div class=\"visible\">",$m);
			$content=$this->getContent("<br /></span></div><div class=\"visible\">","</div></div>",$m);
			
			$data[]=array("announcement"=>array(
											"id"			=>$id,
											"title"			=>utf8_encode(htmlentities($title)),
											"mailed"		=>$mailed?1:0,
											"visibility"	=>$visibility,
											"created"		=>$created,
											"content"		=>$content,
			));
		}
		
		preg_match("/\<div id=\"users_table_top_right\" class=\"dataTables_filter\">(.*)<\/div>/msU",$c,$match);
		//echo $match[0];
		$posts=$this->getContent("<span class=\"left\">","</span>",$match[0]);
		$posts=explode("/ ",$posts);
		
		$pageing=array(	"page"		=>	$page,
						"perpage"	=>	$perpage,
						"posts"		=>	$posts[1],
						);
		
		//also return total #posts, current page, perpage
		return array(	"announcements"	=>	$data,
						"pageing"		=>	$pageing,);
	}
	
	/**
	 *	Gets the content of a tag
	 */
	public function getContent($start, $end, $search) {
		$n1=explode($start,$search);
		$n2=explode($end,$n1[1]);
		return $n2[0];
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