<?php 
ob_start();
/*
使用说明：
1、使用前请先在下面配置
2、通过?kind=type调用，type是你自己在switch中自定义的case类型
3、处理完文件后返回json数据
4、成功返回形如：
{"code":200,"name":"原始文件名","save_name"=>"服务器保存文件名","path"=>"保存路径","msg":"上传文件[文件名]成功！"}
5、失败返回形如 如下三种json数据：
{"code":403,"msg":"文件大小超出限制！[最大*KB]"}
{"code":403,"msg":"该文件类型不允许上传！只允许：扩展名"}
{"code":500,"msg":"上传失败！错误代码:"数字"}
小提示：
由于ajax不能上传文件，而且本程序返回数据是json格式，所以建议使用jquery.form.js插件提交表单，
该插件动态创建iframe提交表单，并支持返回json数据，详细使用请看官方的上传文件示例。
另外，如果使用jquery.form.js
要注意修改下面的这句代码echo $buffer;为echo '<textarea>'.$buffer.'</textarea>';
这是因为jquery.form.js插件要求返回的json数据必须是在<textarea>标记之间。
*/
//##########################配置开始#########################

$isRnd=true;//true是使用随机文件名保存文件，false使用原始文件名或者用户自己指定的文件名保存
$formFileFieldName="upfiledata";//表单中文件标签的name属性值
switch(@$_GET['kind']){
//请根据case配置不同的上传信息
 case "type_1":
     $upfilePath = "../attachment/dish/";//上传文件保存路径,文件夹不存在时会尝试新建,最后要有 /
     $Exts=array(".jpg",".png",".bmp",".gif");//允许上传文件后缀，留空允许所有
     $file_size = 2048; //上传文件大小单位KB
     break;  
 case "type_2":
 	 $upfilePath = "../attachment/dish/";
     $Exts=array(".jpg",".png",".bmp",".gif");
     $file_size = 5000; 
     
     break;
 default:
 	 exit();
}
//################################配置结束#############################

//以下代码不要改动
$successEcho=json_encode(array("code"=>200,"name"=>"[name]","save_name"=>"[save_name]","path"=>"[path]","msg"=>"上传文件[name]成功！"));
@$file=$_FILES[$formFileFieldName];
$fileobj = new upload($file);
$fileobj->isRnd=$isRnd;//设置是否使用随机文件名
$fileobj->file_size=$file_size;//设置文件大小
$fileobj->upfilePath = $upfilePath;//设置上传路径
$fileobj->successEcho=$successEcho;//设置上传成功返回的json数据
$fileobj->setExt($Exts);//设置允许的文件后缀
$fileobj->saveFile();//处理文件

$name=$fileobj->filename;
$saveName=$fileobj->saveName;
$path=$fileobj->upfilePath;
$buffer=ob_get_contents();ob_clean();
$buffer=str_replace("[name]",$name,$buffer);
$buffer=str_replace("[save_name]",$saveName,$buffer);
$buffer=str_replace("[path]",$path,$buffer);
echo $buffer;
?>
<?php
/**
 * 本文件是ＰＨＰ文件上传类，作学习交流之用
 * 任何人和组织可以自由复制和修改
 * 但是请保留该版权信息
 * 作者：狂奔的蜗牛 QQ：672308444 [2010-8-17 4:52:28]
 */


class upload
{  
    public $file; //通过$_FILES["upload"]获得的文件对象
    public $file_size = 1024; //上传最大值，单位KB，默认是：1024KB（1M）最大不能超过php.ini中的限制（默认是2M）
    public $upfilePath = "../attachment/dish/"; //上传文件路径，如果文件夹不存在自动创建
    private $ext = array(".jpg",".png"); //文件类型限制，默认是：".gif",".jpg",".png",".bmp"
    public $saveName,$filename,$isRnd=true;
    public function __construct($files)
    {   @session_start();
        $this->file = $files;
    }

    function saveFile($saveName = null)
    {	
        $upPath = $this->upfilePath;
        $file = $this->file;
        $this->filename=$file["name"];
        if (!$this->isRnd) {
        	if(!$saveName){$saveName=$this->filename;}
        	$this->saveName=$saveName;
            $path = $upPath.$saveName;
        } elseif($this->isRnd){
            $saveName=$this->getRandomName();
            $path = $upPath.$saveName;
            $this->saveName=$saveName;
        }

        if (!file_exists($upPath)) {
            mkdir($upPath);
        }
        if ($this->check_file_type($file)) {
            echo '{"code":403,"msg":"该文件类型不允许上传！只允许：'.str_replace('"',"",json_encode($this->ext)).'"}';
            return;
        }
        if ($this->check_file_size($file)) {
        	echo '{"code":403,"msg":"文件大小超出限制！[最大'.$this->file_size.'KB]"}';
            return;
        }
        $newPath = iconv("UTF-8", "gbk", $path);

        @move_uploaded_file($file["tmp_name"], $newPath);
        if($file["error"]===0){
        	$this->ImageToJPG($newPath,$newPath,500,300);
        echo $this->successEcho;
        }else{
        echo '{"code":500,"msg":"上传失败！错误代码:'.$file["error"].'"}';
        }
    }

    function setExt(array $ext)
    {
        $this->ext = $ext;
    }
    function getExt(array $ext)
    {
        return $this->ext;
    }
    function getRandomName()
    {
        @$pos = strrpos($this->file["name"], ".");
        @$fileExt = strtolower(substr($this->file["name"], $pos));
        ini_set('date.timezone', 'Asia/Shanghai');
        $t = getdate();
        @$year = $t[year];
        @$mon = $t[mon] > 10 ? $t[mon] : "0".$t[mon];
        @$day = $t[day] > 10 ? $t[day] : "0".$t[day];
        @$hours = $t[hours] > 10 ? $t[hours] : "0".$t[hours];
        @$minutes = $t[minutes] > 10 ? $t[minutes] : "0".$t[minutes];
        @$seconds = $t[seconds] > 10 ? $t[seconds] : "0".$t[seconds];
        return $year.$mon.$day.$hours.$minutes.$seconds.rand(1000, 9999).$fileExt;
    }

    function check_file_type()
    {
        @$file = $this->file;
        @$ext = $this->ext;
        @$pos = strrpos($file["name"], ".");
        @$fileExt = strtolower(substr($file["name"], $pos));
        if (count($ext) == 0)
            return false;
        for ($i = 0; $i < count($ext); $i++) {
            if (strcmp($ext[$i], $fileExt) == 0) {
                return false;
            }
            ;
        }
        return true;
    }
    function check_file_size()
    {
        $file = $this->file;
        if (@$file["size"] / 1024 <= $this->file_size)
            return false;
        return true;
    }
    
    
    function ImageToJPG($srcFile,$dstFile,$towidth,$toheight)   
{   
    $quality=50;   
    $data = @GetImageSize($srcFile);   
    switch ($data['2'])   
    {   
    case 1:   
        $im = imagecreatefromgif($srcFile);   
        break;   
    case 2:   
        $im = imagecreatefromjpeg($srcFile);   
        break;   
    case 3:   
        $im = imagecreatefrompng($srcFile);   
        break;   
    case 6:   
    $im = ImageCreateFromBMP( $srcFile );   
    break;   
    }  
    // $dstX=$srcW=@ImageSX($im);   
    // $dstY=$srcH=@ImageSY($im);   
    $srcW=@ImageSX($im);   
    $srcH=@ImageSY($im);   
    //$towidth,$toheight  
    if($toheight/$srcW > $towidth/$srcH){  
        $b = $toheight/$srcH;  
    }else{  
        $b = $towidth/$srcW;  
    }     
    //计算出图片缩放后的宽高  
    // floor 舍去小数点部分，取整  
    $new_w = floor($srcW*$b);  
    $new_h = floor($srcH*$b);  
    $dstX=$new_w;   
    $dstY=$new_h;   
    $ni=@imageCreateTrueColor($dstX,$dstY);   
  
    @ImageCopyResampled($ni,$im,0,0,0,0,$dstX,$dstY,$srcW,$srcH);   
  	switch ($data['2'])   
    {     
    case 2:   
        @ImageJpeg($ni,$dstFile,$quality);
        break;   
    case 3:   
        @ImagePng($ni,$dstFile,$quality);
        break;     
    }   
    @imagedestroy($im);   
    @imagedestroy($ni);   
    //www.veryhuo.com/a/view/36032.html  
}   
    
    
    
    
    
    
}//end class
?>