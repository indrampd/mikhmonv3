<?php
// Copy Paste ke template editor [Settings -> Template Editor].
if(substr($validity,-1) == "d"){
   $validity = "   <br>Aktif : ".substr($validity,0,-1)." HARI";
}else if(substr($validity,-1) == "h"){
   $validity = "Aktif : ".substr($validity,0,-1)." JAM";
}
if(substr($timelimit,-1) == "d" & strlen($timelimit) >3){
   $timelimit = "Durasi : ".((substr($timelimit,0,-1)*7) +  substr($timelimit, 2,1))." HARI";
}else if(substr($timelimit,-1) == "d"){
   $timelimit = "Durasi : ".substr($timelimit,0,-1)." HARI";
}else if(substr($timelimit,-1) == "h"){
   $timelimit = "Durasi : ".substr($timelimit,0,-1)." JAM";
}else if(substr($timelimit,-1) == "w"){
   $timelimit = "Durasi : ".(substr($timelimit,0,-1)*7)." HARI";
}	            	            

/* 
Sesuikan harga dan warna masing-masing.
warna bisa dilihat di https://material.io/guidelines/style/color.html#color-color-palette
variable $color
background-color:<?php echo $color;?>; -webkit-print-color-adjust: exact;
ditambahkan ke style di tag html yang ingin dikasi warna. untuk template ini warna ditaruh di keterangan harga <-- Price
--> line 81
  */

if($getprice == "1000"){ $color = "#FF1493";} // jika harga == "1000" maka warna = "#01579B"
   elseif($getprice == "1000"){ $color = "#FF1493";}
   elseif($getprice == "2000"){ $color = "#008000";} //#8B008B
   elseif($getprice == "3000" && $timelimit == "Durasi : 12 JAM"){ $color = "#FF0000";}
   elseif($getprice == "3000"){ $color = "#666";}
   elseif($getprice == "4000" && $timelimit == "Durasi : 20 JAM"){ $color = "#FF0000";}
   elseif($getprice == "4000"){ $color = "#228B22";} //#228B22
   elseif($getprice == "10000"){ $color = "#E65100";}
   elseif($getprice == "15000"){ $color = "#FF0000";} //#FF0000
   elseif($getprice == "25000"){ $color = "#8B008B";} //#008000
   elseif($getprice == "30000"){ $color = "#FF00FF";}
   elseif($getprice == "60000"){ $color = "#E60C00";}
   // ini yang dicopy untuk menambah warna berdarsarkan harga, kemudian paste di atas baris // else color

   // else color
   else{ $color = "#228B22";}
   ?>
   <!--mks-mulai-->
   <style>
   .qrcode {
      height: 60px;
      width: 60px;
      }
   </style>
   <table style="display: inline-block;border-collapse: collapse;border: 1px solid #666;margin: 2.5px;width: 190px;overflow:hidden;position:relative;padding: 0px;margin: 2px;border: 1px solid #000000;">
      <tbody>
      <tr>
        <td style="color:#666;" valign="top">
          <table style="width:100%;">
            <tbody>
              <tr>
                <td style="width:65px">
                  <div style="position:relative;z-index:-1;padding: 0px;float:left;">
                    <div
                      style="position:absolute;top:0;display:inline;margin-top:-100px;width: 0; height: 0; border-top: 230px solid transparent;border-left: 50px solid transparent;border-right:140px solid #DCDCDC; ">
                    </div>
                  </div>

                  <div style="margin:5px 0 0 5px; width:86px; height:28px">
                     <svg version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg"
                        xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 383.2 128"
                        style="enable-background:new 0 0 383.2 128;" xml:space="preserve">
                        <style type="text/css">
                           .st0 {
                              fill: #F1F2F2;
                              fill-opacity: 0;
                           }

                           .st1 {
                              fill: <?= $color ?>;
                           }

                           .st2 {
                              fill: #231F20;
                           }
                        </style>
                        <g id="Layer_2_1_">
                           <rect class="st0" width="383.2" height="128" />
                           <path class="st1" d="M92.1,71.3c-3.7,3.4-4,9.1-0.6,12.8c0.2,0.2,0.4,0.4,0.6,0.6c4,3.7,10.4,3.7,14.3,0c3.7-3.4,4-9.1,0.6-12.8
		c-0.2-0.2-0.4-0.4-0.6-0.6C102.5,67.6,96,67.6,92.1,71.3z" />
                           <path class="st1"
                              d="M99.2,46.3c-8.3,0-16.2,3-22.1,8.6c-2.2,2-2.3,5.3-0.4,7.5c0.1,0.1,0.2,0.2,0.4,0.4c2.4,2.2,6,2.2,8.4,0
		c7.8-7.1,19.6-7.1,27.4,0c2.4,2.2,6,2.2,8.4,0c2.2-2,2.3-5.3,0.4-7.5c-0.1-0.1-0.2-0.2-0.4-0.4C115.4,49.3,107.6,46.3,99.2,46.3z" />
                           <path class="st1" d="M99.2,28C85.7,28,72.9,33,63.4,41.9c-2.2,2-2.3,5.3-0.4,7.5c0.1,0.1,0.2,0.2,0.4,0.4c2.4,2.2,6,2.2,8.4,0
		c7.3-6.9,17.1-10.7,27.5-10.7s20.2,3.8,27.5,10.7c2.4,2.2,6,2.2,8.4,0c2.2-2,2.3-5.3,0.4-7.5c-0.1-0.1-0.2-0.2-0.4-0.4
		C125.6,33,112.8,28,99.2,28z" />
                           <path class="st1" d="M149,29C135.7,16.5,118,9.7,99.2,9.7S62.8,16.5,49.5,29c-2.2,2-2.3,5.3-0.4,7.5c0.1,0.1,0.2,0.2,0.4,0.4
		c2.4,2.2,6,2.2,8.4,0c11-10.3,25.7-16,41.3-16s30.3,5.7,41.3,16c2.4,2.2,6,2.2,8.4,0c2.2-2,2.3-5.3,0.3-7.5
		C149.2,29.2,149.1,29.1,149,29z" />
                           <path class="st2" d="M44,99.7V76c0-3.1-0.5-5.3-1.4-6.6c-0.8-1.2-2.4-1.8-4.8-1.8c-1.7,0-3.4,0.6-4.8,1.6c-1.4,1-2.4,2.3-2.9,3.9
		l-0.1,0.2v26.4H16.5v-58h13.6v19.2l2.1-1.4c0.7-0.4,1.4-0.9,2.1-1.2c2.2-1.1,5.1-1.6,8.5-1.6c2.1,0,4.3,0.3,6.3,0.8
		c1.8,0.5,3.5,1.5,4.8,2.9c1.2,1.3,2.2,3.1,2.8,5.4c0.6,2.2,0.9,4.9,0.9,8v25.9H44z" />
                           <path class="st2" d="M165.9,100.8c-4.4,0-7.7-1.1-9.8-3.1s-3.2-5.3-3.2-9.9V68.3h-6.2V57.8h6.2v-7.4l13.6-3.5v10.8h11v10.6h-11
		v14.6c0,2.6,0.3,4.6,0.9,5.6c0.3,0.6,0.9,1.3,2.7,1.3c1,0,1.9-0.1,2.8-0.3c0.9-0.2,1.9-0.6,2.8-0.9l1.1-0.4l2.5,9.7l-0.7,0.3
		c-1.9,0.8-3.8,1.4-5.8,1.8C170.4,100.6,168.2,100.8,165.9,100.8z" />
                           <path class="st2"
                              d="M196.5,100.8c-3.1,0-6.2-0.4-9.2-1.1c-2.9-0.8-5.2-1.6-7-2.6l-0.7-0.4l3.9-9.4l1,0.5c1.7,0.8,3.5,1.5,5.3,2
		c2.2,0.6,4.4,1,6.7,1c1.5,0,2.8-0.2,3.7-0.8c0.8-0.4,1.1-1.2,1.1-2.2c0-0.8-0.4-1.4-1.1-1.9c-1.1-0.7-2.3-1.2-3.6-1.7
		c-1.5-0.5-3.2-1.1-4.9-1.6c-1.8-0.6-3.6-1.4-5.2-2.5c-1.6-1.1-3-2.4-4-4c-1.1-1.7-1.7-3.8-1.7-6.4c0-4.2,1.4-7.5,4.3-9.7
		s6.9-3.4,12.1-3.4c3.2,0,6.4,0.3,9.5,1c2.9,0.7,5.1,1.4,6.7,2.2l0.7,0.3l-3.1,9.1l-1-0.3c-1.3-0.5-3.1-1-5.1-1.5
		c-2-0.5-4.1-0.8-6.1-0.8c-2.9,0-4.2,0.9-4.2,2.8c0,0.7,0.3,1.2,1.1,1.6c1.1,0.6,2.3,1.2,3.6,1.6c1.5,0.5,3.1,1,4.9,1.6
		c1.8,0.6,3.5,1.4,5.2,2.4c1.6,1,3,2.3,4,3.9c1.1,1.6,1.7,3.7,1.7,6.2c0,4.3-1.6,7.8-4.7,10.2S202.6,100.8,196.5,100.8z" />
                           <path class="st2" d="M220.7,115.8v-58h10.2l1.1,3.9l1.7-1.4c1-0.8,2.1-1.5,3.3-2.1c2.2-1,5-1.5,8.2-1.5c5.9,0,10.4,1.7,13.4,5.1
		c3,3.4,4.4,8.6,4.4,15.9c0,3.2-0.4,6.4-1.4,9.5c-0.8,2.7-2.3,5.2-4.2,7.3c-1.9,2.1-4.2,3.6-6.8,4.7c-2.7,1.1-5.8,1.6-9.2,1.6
		c-1.6,0-3.2-0.1-4.8-0.4c-0.2,0-0.5-0.1-0.7-0.2l-1.7-0.4v15.9H220.7z M241.7,67.2c-2,0-3.6,0.4-4.7,1.3s-2,2.2-2.6,3.9l-0.1,0.2
		v16.2l0.6,0.3c0.5,0.3,1,0.5,1.5,0.7c0.9,0.3,2.1,0.5,3.6,0.5c3.1,0,5.3-0.9,6.8-2.8c1.6-2,2.3-5.3,2.3-10c0-3.4-0.6-6-1.8-7.8
		C246.1,68,244.2,67.2,241.7,67.2z" />
                           <path class="st2" d="M289.2,100.8c-3.7,0-7-0.5-9.7-1.6c-2.6-1-5-2.5-7-4.5c-1.9-2-3.3-4.4-4.1-7c-0.9-2.9-1.4-5.9-1.3-8.9
		c0-7,2-12.5,5.8-16.3s9.4-5.8,16.4-5.8c3.7,0,7,0.5,9.7,1.6c2.6,1,5,2.5,6.9,4.5c1.9,2,3.3,4.4,4.1,7c0.9,2.9,1.4,5.9,1.3,8.9
		c0,7-1.9,12.5-5.8,16.3S296.2,100.8,289.2,100.8z M289.2,67.2c-2.6,0-4.5,0.8-6,2.6c-1.5,1.8-2.2,4.8-2.2,9c0,1.6,0.2,3.2,0.5,4.7
		c0.3,1.3,0.8,2.6,1.5,3.7c0.6,1,1.5,1.8,2.5,2.3c1.1,0.6,2.4,0.8,3.7,0.8c2.8,0,4.7-0.8,6-2.6c1.4-1.8,2.1-4.8,2.1-8.9
		c0-3.5-0.7-6.4-2-8.6C294.2,68.2,292.1,67.2,289.2,67.2z" />
                           <path class="st2" d="M332,100.8c-4.4,0-7.7-1.1-9.8-3.1s-3.2-5.3-3.2-10V68.3h-6.2V57.8h6.2v-2.3h2.7l0.4-0.3
		c0.7-0.6,1.4-1.2,2-1.9c2.5-2.5,3.2-3.1,3.5-3.2l0.3-0.2l0.3,0c0.4,0,0.6,0.2,1.4,1c0.5,0.5,1.2,1.2,2.2,2.1l0.5,0.5l0.2,0.2v4.1
		h11v10.6h-11v14.6c0,2.6,0.3,4.6,0.9,5.6c0.3,0.6,0.9,1.3,2.7,1.3c1,0,1.9-0.1,2.8-0.3c0.9-0.2,1.9-0.6,2.8-0.9l1.1-0.4l2.5,9.7
		l-0.7,0.3c-1.9,0.8-3.8,1.4-5.8,1.8C336.6,100.6,334.3,100.8,332,100.8z" />
                           <rect x="350.6" y="56.6" class="st1" width="15.8" height="13" />
                           <path class="st2" d="M306.2,49.7c0,0.3-0.4,0.4-1.2,0.4h-9.5c-0.3,0-1.5-0.9-3.4-2.8c-1.9-1.9-2.9-2.8-3-2.8
		c-0.4,0.2-1.5,1.2-3.3,3.1c-1.6,1.7-2.7,2.5-3,2.5h-9.5c-0.8,0-1.2-0.1-1.2-0.4v-6.5c0-0.3,1.2-1.5,3.6-3.5c2.4-2,3.6-3.2,3.6-3.7
		c0-0.4-1.2-1.7-3.6-3.7s-3.6-3.2-3.6-3.5c0-0.3,0-0.9,0-1.6s0-1.2,0-1.6s0-1-0.1-1.8s-0.1-1.4-0.1-1.8c0-0.3,0.4-0.4,1.2-0.4
		c0.6,0,1.4,0.1,2.6,0.2c1.2,0.1,2,0.2,2.6,0.2c0.5,0,1.2,0,2.2,0s1.7,0,2.2,0c0.4,0,1.5,0.9,3.4,2.8c1.9,1.9,2.9,2.8,3,2.8
		c0.3-0.2,1.5-1.2,3.4-3.1c1.8-1.7,2.8-2.5,3.2-2.5h9.2c0.8,0,1.2,0.1,1.2,0.4c0,0.4,0,0.9-0.1,1.7c-0.1,0.8-0.1,1.3-0.1,1.7
		s0,0.9,0.1,1.7s0.1,1.3,0.1,1.6c0,0.4-1.2,1.7-3.6,3.8c-2.4,2.1-3.6,3.2-3.6,3.2c0,0.3,1.2,1.4,3.7,3.5s3.7,3.3,3.7,3.6L306.2,49.7
		z" />
                           <path class="st2" d="M345.3,49.7c0,0.3-0.4,0.4-1.2,0.4h-9.5c-0.3,0-1.5-0.9-3.4-2.8c-1.9-1.9-2.9-2.8-3-2.8
		c-0.4,0.2-1.5,1.2-3.3,3.1c-1.6,1.7-2.7,2.5-3,2.5h-9.5c-0.8,0-1.2-0.1-1.2-0.4v-6.5c0-0.3,1.2-1.5,3.6-3.5s3.6-3.2,3.6-3.7
		c0-0.4-1.2-1.7-3.6-3.7s-3.6-3.2-3.6-3.5c0-0.3,0-0.9,0-1.6s0-1.2,0-1.6s0-1-0.1-1.8s-0.1-1.4-0.1-1.8c0-0.3,0.4-0.4,1.2-0.4
		c0.6,0,1.4,0.1,2.6,0.2s2,0.2,2.6,0.2c0.5,0,1.2,0,2.2,0c1,0,1.7,0,2.2,0c0.4,0,1.5,0.9,3.4,2.8c1.9,1.9,2.9,2.8,3,2.8
		c0.3-0.2,1.5-1.2,3.4-3.1c1.8-1.7,2.8-2.5,3.2-2.5h9.2c0.8,0,1.2,0.1,1.2,0.4c0,0.4,0,0.9-0.1,1.7c-0.1,0.8-0.1,1.3-0.1,1.7
		s0,0.9,0.1,1.7s0.1,1.3,0.1,1.6c0,0.4-1.2,1.7-3.6,3.8c-2.4,2.1-3.6,3.2-3.7,3.2c0,0.3,1.2,1.4,3.7,3.5c2.5,2.1,3.7,3.3,3.7,3.6
		L345.3,49.7z" />
                           <path class="st2"
                              d="M366.7,49.5c0,0.4-0.3,0.6-0.8,0.6c-0.8,0-2.1,0-3.8-0.1c-1.7-0.1-2.9-0.1-3.8-0.1c-0.8,0-2,0-3.6,0.1
		c-1.6,0.1-2.8,0.1-3.6,0.1c-0.6,0-1-0.2-1-0.6c0-1.6,0.1-4.1,0.3-7.4c0.2-3.3,0.3-5.8,0.3-7.4c0-1.4-0.1-3.4-0.3-6.2
		c-0.2-2.8-0.3-4.8-0.3-6.2c0-0.3,0.2-0.4,0.7-0.4c0.8,0,2.1,0,3.8,0.1s3,0.1,3.9,0.1c0.8,0,2,0,3.6-0.1s2.8-0.1,3.6-0.1
		c0.5,0,0.8,0.2,0.8,0.5c0,1.4-0.1,3.4-0.3,6.2c-0.2,2.7-0.3,4.8-0.3,6.2c0,1.6,0.1,4.1,0.3,7.4C366.6,45.4,366.7,47.9,366.7,49.5z" />
                        </g>
                     </svg>

                  </div>

                  
                  <!-- <img style="margin:5px 0 0 5px;" width="86" height="28" src="<?php echo $logo;?>" alt="logo"> -->
                </td>
                <td style="width:105px">
                  <div style="float:right;margin-top:-6px;margin-right:0px;width:5%;text-align:right;font-size:7px;">
                  </div>
                  <div
                     style="text-align:right;font-weight:bold;font-family:Tahoma;font-size:20px;padding-left:17px;color:<?php echo $color ?>">
                     <small
                        style="font-size:10px;margin-left:-17px;position:absolute;">Rp.</small><?php echo $getprice;?>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </td>
      </tr>
      <tr>
        <td style="color:#666;border-collapse: collapse;" valign="top">
          <table style="width:100%;border-collapse: collapse;">
            <tbody>
              <tr>
                <td style="width:95px" valign="top">
                  <div style="clear:both;color:#555;margin-top:2px;margin-bottom:2.5px;">
                     <?php if($v_opsi=='up'){ ?>
                     <?php }else{ ?>
                     <div
                        style="padding:0px;border-bottom:1px solid<?php echo $color ?>;text-align:center;font-weight:bold;font-size:10px;">
                        VOUCHER</div>
                     <div
                        style="padding:0px;border-bottom:1px solid<?php echo $color ?>;text-align:center;font-weight:bold;font-size:16px;;color:#000000;">
                        <?php echo $username;?></div>
                     <?php } ?>
                  </div>
                  <div style="text-align:center;color:#111;font-size:7px;font-weight:bold;margin:0px;padding:2.5px;">
                     Hubungkan ke WiFi<br>
                     Buka browser<br>
                     ketik : 192.168.88.1
                  </div>
                </td>
                <td style="width:100px;text-align:right;">
                  <div style="clear:both;padding:0 2.5px;font-size:7px;font-weight:bold;color:#000000">
                    <?php echo $validity;?><br> <?php echo $timelimit;?> <br><?php echo $datalimit;?>
                  </div>
                  <div style="float:right;padding:1px;text-align:right;width:70%;margin:0 5px -20px 0;"><?= $qrcode ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td style="background:<?php echo $color ?>;color:#666;padding:0px;" valign="top" colspan="2">
                  <div style="text-align:left;color:#fff;font-size:9px;font-weight:bold;margin:0px;padding:2.5px;">
                    Tanggal Print : <?= date("d-m-Y") ?>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </td>
      </tr>
    </tbody>
  </table>
  <!--mks-akhir-->	        	        	        	        	        	        	        	        	        