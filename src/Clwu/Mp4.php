<?php
// Copyright (c) 2018, chaolong.wu@qq.com
// All rights reserved.
//
// Redistribution and use in source and binary forms, with or without
// modification, are permitted provided that the following conditions are met:
//     * Redistributions of source code must retain the above copyright
//       notice, this list of conditions and the following disclaimer.
//     * Redistributions in binary form must reproduce the above copyright
//       notice, this list of conditions and the following disclaimer in the
//       documentation and/or other materials provided with the distribution.
//     * Neither the name of the <organization> nor the
//       names of its contributors may be used to endorse or promote products
//       derived from this software without specific prior written permission.
//
// THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
// ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
// WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
// DISCLAIMED. IN NO EVENT SHALL <COPYRIGHT HOLDER> BE LIABLE FOR ANY
// DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
// (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
// LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
// ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
// (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
// SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

namespace Clwu;

class Mp4
{

    /**
     * 返回 mp4 视频的 rotate/width/height ...
     *
     * @param    $mp4_file_path
     *
     * @return   int
     *
     * @author  clwu
     * @date    Tue Apr 17 19:39:16 UTC 2018
     *
     * <code>
     *      var_dump( \Clwu\Mp4::getInfo($argv[1]) );
     * </code>
     */
    public static function getInfo($mp4_file_path)
    {
        $width    = 0;
        $height   = 0;
        $duration = 0;
        $rotate   = 0;
        $decoded_len = 0; // 已经解码的文件长度
        $err = 0;

        $fd = fopen($mp4_file_path, 'r');

        $file_info = fstat($fd);
        $total_len = $file_info['size']; // 文件总长度

        do {
            $err = fseek($fd, $decoded_len, SEEK_SET);

            $buffer = fread($fd, 8); // read in box header
            $box_header = unpack('Nsize/a4type', $buffer);

            // var_dump($box_header);
            $size = $box_header['size'];
            $type = $box_header['type'];

            $is_extended_size = (1 == $box_header['size']); // 64 bit extended size

            if ($is_extended_size) { // 64 bit extended size
                $buffer = fread($fd, 8); // read in 64 bit extended size
                $size  = self::unpack_u64($buffer);
            }

            if ("moov" == $type) {
                $decoded_len += 8 + ($is_extended_size ? 8 : 0); // 进入这个container box
                continue; // continue fread mvhd
            } else if ("trak" == $type) {
                $decoded_len += 8 + ($is_extended_size ? 8 : 0); // 进入这个container box
                continue; // continue fread tkhd
            } else if ("tkhd" == $type) {
                // tkhd 的结构 @see http://blog.sina.com.cn/s/blog_48f93b530100jz4b.html
                $buffer = fread($fd, 1); // version
                fread($fd, 3);           // flags
                $version = current( unpack('c', $buffer) );
                if (1 == $version) {
                    fread($fd, 8); // 64bit creation time
                    fread($fd, 8); // 64bit modification time
                    $d_len = 8;
                } else {
                    fread($fd, 4); // 32bit creation time
                    fread($fd, 4); // 32bit modification time
                    $d_len = 4;
                }
                              fread($fd, 4);       // track id id号，不能重复且不能为0
                $_timescale = fread($fd, 4);       // 用来指定文件媒体在1秒时间内的刻度值，可以理解为1秒长度的时间单元数
                $_duration  = fread($fd, $d_len);  // duration track的时间长度
                              fread($fd, 8);       // reserved 保留位
                              fread($fd, 2);       // layer 视频层，默认为0，值小的在上层
                              fread($fd, 2);       // alternate group track分组信息，默认为0表示该track未与其他track有群组关系
                              fread($fd, 2);       // volume [8.8] 格式，如果为音频track，1.0（0x0100）表示最大音量；否则为0
                              fread($fd, 2);       // reserved 保留位
                $matrix     = fread($fd, 36);      // matrix 视频变换矩阵
                $_width     = fread($fd, 4);       // width 宽
                $_height    = fread($fd, 4);  // height 高，均为 [16.16] 格式值，与sample描述中的实际画面大小比值，用于播放时的展示宽高

                $_width  = current( unpack('n2', $_width) );  // [16.16] 格式值
                $_height = current( unpack('n2', $_height) ); // [16.16] 格式值

                // // 用duration和time scale值可以计算track时长
                // $_timescale = current( unpack('N', $_timescale) );
                // if (4 == $d_len) {
                //     $_duration  = current( unpack('N', $_duration) );
                // } else {
                //     $_duration  = self::unpack_u64($_duration);
                // }

                // 有可能出现多次 tkhd，只取有效值
                if ($_width || $_height) {
                    $width    = $_width;
                    $height   = $_height;
                    //$duration = $_duration / $_timescale; // 用duration和time scale值可以计算track时长
                }

                $matrix = unpack('N9', $matrix); // unpack 没有参数可以转换为 signed long (always 32 bit, big endian byte order)，在下面的比较中需要把 有符号-65536 转换为 无符号4294901760
                $display_matrix = [
                  [ $matrix[1], $matrix[2], $matrix[3] ],
                  [ $matrix[4], $matrix[5], $matrix[6] ],
                  [ $matrix[7], $matrix[8], $matrix[9] ],
                ];

                // Assign clockwise rotate values based on transform matrix so that
                // we can compensate for iPhone orientation during capture.
                if ($display_matrix[1][0] == 4294901760/* -65536 */ && $display_matrix[0][1] == 65536) {
                    $rotate = 90;
                    break;
                }
                if ($display_matrix[0][0] == 4294901760/* -65536 */ && $display_matrix[1][1] == 4294901760/* -65536 */) {
                    $rotate = 180;
                    break;
                }
                if ($display_matrix[1][0] == 65536 && $display_matrix[0][1] == 4294901760/* -65536 */) {
                    $rotate = 270;
                    break;
                }
            }

            $decoded_len += $size;
        } while( $decoded_len < $total_len );

        fclose($fd);

        return [
            'rotate'   => $rotate,
            'width'    => $width,
            'height'   => $height,
            //'duration' => $duration,
            // TODO: 更多的info 字段
        ];
    }

    private static function unpack_u64($str)
    {
        // $size = array_pop( unpack('J', $buffer) ); // PHP Warning:  unpack(): Invalid format type J
        // v_v 公司用的PHP5.5 不支持 J 参数，用下面方式得到64bit的长度值，与上面一句注释了的代码一样效果
        $num64 = unpack('N2', $str);
        $size = ($num64[1] << 32) | $num64[2];

        return $size;
    }
}
