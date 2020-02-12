# php-read-mp4info
返回 iPhone 拍摄的 mp4 视频的 rotate

用 php 实现了分析 mp4 视频文件的格式，但目前只实现了返回视频的的旋转度数。

![代码例子](https://gitee.com/uploads/images/2018/0419/121428_883f6648_556749.png "QQ图片20180419092233.png")

![手机旋转示例](https://gitee.com/uploads/images/2018/0419/121526_843b6a41_556749.png "QQ图片20180419121458.png")

## composer.json
```json
    "require": {
        "clwu/php-read-mp4info": "v2.0.0"
    }
```

```php
<?php
require('vendor/autoload.php');
var_dump(\Clwu\Mp4::getInfo('/tmp/faae8ca03b6e6c06cf47dad6dde46830.mp4'));


array(3) {
  ["rotate"]=> int(0)
  ["width"]=> int(960)
  ["height"]=> int(544)
}
```
