<?php defined('COT_CODE') or die('Wrong URL');
/* ====================
Copyright (c) 2008-2013, Vladimir Sibirov, Skuola.net and Seditio.By.
All rights reserved. Distributed under BSD License.

[BEGIN_COT_EXT]
Code=attach2
Name=Attachments
Category=files-media
Description=Attach files to posts and pages
Version=2.1.7
Date=2014-01-14
Author=Trustmaster
Copyright=(c) Vladimir Sibirov, Skuola.net and Seditio.By, 2008-2013
Notes=DO NOT FORGET to create a writable folder for attachments
SQL=
Auth_guests=R1
Lock_guests=2345A
Auth_members=RW1
Lock_members=2345
[END_COT_EXT]

[BEGIN_COT_EXT_CONFIG]
folder=01:string::datas/attach:Directory for files
prefix=02:string::att_:File prefix
exts=03:text::gif,jpg,jpeg,png,zip,rar,7z,gz,bz2,pdf,djvu,mp3,ogg,wma,avi,divx,mpg,mpeg,swf,txt:Allowed extensions (comma separated, no dots and spaces)
thumbs=04:radio::1:Display image thumbnails
thumb_x=05:string::160:Default thumbnail width
thumb_y=06:string::160:Default thumbnail height
thumb_framing=08:select:height,width,auto,crop:auto:Default thumbnail framing mode
upscale=10:radio::0:Upscale images smaller than thumb size
thumb_watermark=12:string:::Add watermark for thumbs (Filename. Empty for disable)
thumb_wm_x=14:string::200:Image max width for resize
thumb_wm_y=16:string::200:Image max width for resize
img_resize=18:radio::0:auto:Resize uploaded images
img_maxwidht=20:string::1920:Image max width for resize
img_maxheight=22:string::1080:Image max height for resize
items=24:string::8:Default attachments count per post (max.), 0 - unlimited
chunkSize=26:string::2000000:Chunk size (in bytes) (0 - Disable chunked file uploads)
quality=28:string::85:JPEG quality in %
accept=30:text:::Accepted MIME types in file selection dialog, comma separated. Empty means all types.
filesize=32:string::4194304:Max file size in bytes
filespace=34:string::104857600:Total file space per user
autoupload=41:radio::0:Start uploading automatically
sequential=42:radio::0:Sequential uploading instead of concurrent
imageconvert=51:radio::0:Convert all images to JPG on upload
[END_COT_EXT_CONFIG]
==================== */
