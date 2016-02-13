<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

// still here for EE2 support
$plugin_info = array(
    'pi_name' => 'ImageSizer',
    'pi_version' => '3.0.2',
    'pi_author' => 'David Rencher, Christian Maloney, Roger Hughes, Stephen Sweetland',
    'pi_author_url' => 'http://www.lumis.com/',
    'pi_description' => 'Image Resizer - resizes and caches images',
    'pi_usage' => Imgsizer::usage(),
);
// end still here

class Imgsizer {

    public $return_data = "";

    function __construct() {
        $this->EE = &get_instance();
        $this->EE->load->helper('string');
        $this->tag_vars = '';
    }

    // still here for EE2 support
    function Imgsizer() {
        $this->EE = &get_instance();
        $this->EE->load->helper('string');
        $this->tag_vars = '';
    }
    // end still here

    function size() {
        // --------------------------------------------------
        //  Set any output tag vars
        // --------------------------------------------------
        $this->tag_vars .= (!$this->EE->TMPL->fetch_param('alt')) ? '' : ' alt="'.$this->EE->TMPL->fetch_param('alt').'"';
        $this->tag_vars .= (!$this->EE->TMPL->fetch_param('class')) ? '' : ' class="'.$this->EE->TMPL->fetch_param('class').'"';
        $this->tag_vars .= (!$this->EE->TMPL->fetch_param('id')) ? '' : ' id="'.$this->EE->TMPL->fetch_param('id').'"';
        $this->tag_vars .= (!$this->EE->TMPL->fetch_param('style')) ? '' : ' style="'.$this->EE->TMPL->fetch_param('style').'"';
        $this->tag_vars .= (!$this->EE->TMPL->fetch_param('title')) ? '' : ' title="'.$this->EE->TMPL->fetch_param('title').'"';

        // --------------------------------------------------
        //  Determine base path
        // --------------------------------------------------

        // checks if server supports $ENV DOCUMENT_ROOT use $_SERVER otherwise
        if (array_key_exists('DOCUMENT_ROOT', $_ENV)) {
            $base_path = $_ENV['DOCUMENT_ROOT'] . "/";
        } else {
            $base_path = $_SERVER['DOCUMENT_ROOT'] . "/";
        }

        $base_path = str_replace("\\", "/", $base_path);
        $base_path = reduce_double_slashes($base_path);

        // -------------------------------------
        // define some defaults
        // -------------------------------------
        $img = array();
        $okmime = array("image/png", "image/gif", "image/jpeg");

        // -------------------------------------
        // collect passed vars from the tag
        // -------------------------------------
        $src = (!$this->EE->TMPL->fetch_param('image')) ? (!$this->EE->TMPL->fetch_param('src') ? '' : $this->EE->TMPL->fetch_param('src')):$this->EE->TMPL->fetch_param('image');
        $src = str_replace(SLASH, "/", $src); // clean up passed src param
        $src = rawurldecode($src); // Pass raw url string to imgsizer else it will fail on invalid characters

        /*debug*/
        $this->EE->TMPL->log_item("imgsizer.user.src: " . $src);

        $base_path = (!$this->EE->TMPL->fetch_param('base_path')) ? $base_path : $this->EE->TMPL->fetch_param('base_path');
        $base_path = str_replace("\\", "/", $base_path);
        $base_path = reduce_double_slashes($base_path);
        $img['base_path'] = $base_path;

        $cache = (!$this->EE->TMPL->fetch_param('cache')) ? '' : $this->EE->TMPL->fetch_param('cache');

        $refresh = (!$this->EE->TMPL->fetch_param('refresh')) ? '1440' : $this->EE->TMPL->fetch_param('refresh');

        // -------------------------------------
        // if no src thers not much we can do..
        // -------------------------------------
        if (!$src) {
            $this->EE->TMPL->log_item("imgsizer.Error: no src provided for imagesizer");
            return $this->EE->TMPL->no_results();
        }

        // -------------------------------------
        // extract necessities from full URL
        // -------------------------------------
        $remote = false;

        if (substr($src, 0, 4) == 'http') {
            $remote = true;
            $img['url_src'] = $src; // save the URL src for remote
            $urlarray = parse_url($img['url_src']);
            $img['url_host_cache_dir'] = str_replace('.', '-', $urlarray['host']);
            $src = '/' . $urlarray['path'];
        }

        $img['src'] = reduce_double_slashes("/" . $src);
        $img['base_path'] = reduce_double_slashes($base_path);

        /*debug*/
        $this->EE->TMPL->log_item("imgsizer.img[src]: " . $img['src']);
        $this->EE->TMPL->log_item("imgsizer.img[base_path]: " . $img['base_path']);

        // -------------------------------------
        // do fetch remote img and reset the src
        // -------------------------------------
        if ($remote && $img['url_src']) {
            $img['src'] = $this->do_remote($img);
        }

        $img_full_path = reduce_double_slashes($img['base_path'] . $img['src']);

        /*debug*/
        $this->EE->TMPL->log_item("imgsizer.img_full_path: " . $img_full_path);

        // can we actually read this file?
        if (!is_readable($img_full_path)) {
            $this->EE->TMPL->log_item("imgsizer.Error: " . $img_full_path . " image is not readable or does not exist");
            return $this->EE->TMPL->no_results();
        }

        // -------------------------------------
        // get src img sizes and mime type
        // -------------------------------------
        $size = getimagesize($img_full_path);
        $img['src_width'] = $size[0];
        $img['src_height'] = $size[1];
        $img['mime'] = $size['mime'];

        // -------------------------------------
        // get src relitive path
        // -------------------------------------
        $src_pathinfo = pathinfo($img['src']);
        $img['base_rel_path'] = reduce_double_slashes($src_pathinfo['dirname'] . "/");

        /*debug*/
        $this->EE->TMPL->log_item("imgsizer.img[base_rel_path]: " . $img['base_rel_path']);

        // -------------------------------------
        // define all the file location pointers we will need
        // -------------------------------------
        $img_full_pathinfo = pathinfo($img_full_path);
        $img['root_path'] = (!isset($img_full_pathinfo['dirname'])) ? '' : reduce_double_slashes($img_full_pathinfo['dirname'] . "/");
        $img['basename'] = (!isset($img_full_pathinfo['basename'])) ? '' : $img_full_pathinfo['basename'];
        $img['extension'] = (!isset($img_full_pathinfo['extension'])) ? '' : $img_full_pathinfo['extension'];
        $img['base_filename'] = str_replace("." . $img['extension'], "", $img_full_pathinfo['basename']);

        /*debug*/
        foreach ($img_full_pathinfo as $key => $value) {
            $this->EE->TMPL->log_item("imgsizer.full_pathinfo " . $key . ": " . $value);
        }

        // -------------------------------------
        // lets stop this if the image is not in the ok mime types
        // -------------------------------------
        if (!in_array($img['mime'], $okmime)) {
            $this->EE->TMPL->log_item("imgsizer.Error: image mime type does not match ok mime types");
            return $this->EE->TMPL->no_results();
        }

        // -------------------------------------
        // build cache location
        // -------------------------------------
        $base_cache = reduce_double_slashes($base_path . "/images/sized/");
        $base_cache = (!$this->EE->TMPL->fetch_param('base_cache')) ? $base_cache : $this->EE->TMPL->fetch_param('base_cache');
        $base_cache = reduce_double_slashes($base_cache);
        $img['cache_path'] = reduce_double_slashes($base_cache . $img['base_rel_path']);

        if (!is_dir($img['cache_path'])) {
            // make the directory if we can
            if (!mkdir($img['cache_path'], 0777, true)) {
                $this->EE->TMPL->log_item("imgsizer.Error: could not create cache directory! Please manually create the cache directory " . $img['cache_path'] . " with 777 permissions");
                return $this->EE->TMPL->no_results();
            }
        }

        // check if we can put files in the cache directory
        if (!is_writable($img['cache_path'])) {
            $this->EE->TMPL->log_item("imgsizer.Error: " . $img['cache_path'] . " is not writable please chmod 777");
            return $this->EE->TMPL->no_results();
        }

        $img['greyscale'] = (!$this->EE->TMPL->fetch_param('greyscale')) ? '' : $this->EE->TMPL->fetch_param('greyscale');

        if (strtolower($this->EE->TMPL->fetch_param('responsive')) == 'yes') {
            $responsive_sizes = array(320, 768, 992, 1920);
            $svgout = array();
            foreach($responsive_sizes as $rs) {
                $img['auto'] = '';
                $img['autoheight'] = '';
                $img['target_height'] = '9000';
                $img['target_width'] = $rs;
                $img['quality'] = 85;
                // do the sizing math
                $img = $this->get_some_sizes($img);
                // check the cache
                $img = $this->check_some_cache($img);
                // do the sizing if needed
                $img = $this->do_some_image($img);
                $svgout[] = $img;
            }
            return $this->do_output_svg($svgout);
        } else {
            $img['auto'] = (!$this->EE->TMPL->fetch_param('auto')) ? '' : $this->EE->TMPL->fetch_param('auto');
            $img['autoheight'] = (!$this->EE->TMPL->fetch_param('autoheight')) ? '' : $this->EE->TMPL->fetch_param('autoheight');
            $img['quality'] = (!$this->EE->TMPL->fetch_param('quality')) ? '100' : $this->EE->TMPL->fetch_param('quality');
            $img['target_height'] = (!$this->EE->TMPL->fetch_param('height')) ? '9000' : $this->EE->TMPL->fetch_param('height');
            $img['target_width'] = (!$this->EE->TMPL->fetch_param('width')) ? '9000' : $this->EE->TMPL->fetch_param('width');
            // do the sizing math
            $img = $this->get_some_sizes($img);
            // check the cache
            $img = $this->check_some_cache($img);
            // do the sizing if needed
            $img = $this->do_some_image($img);
            /*debug*/
            foreach ($img as $key => $value) {
                $this->EE->TMPL->log_item("imgsizer.img[" . $key . "]: " . $value);
            }
            // do the output
            return $this->do_output($img);
        }

    }

    // -------------------------------------
    // This function calculates how the image should be resized / cropped etc.
    // -------------------------------------
    function get_some_sizes($img) {

        // set some defaults
        $width = $img['src_width'];
        $height = $img['src_height'];
        $img['crop'] = "";
        $img['proportional'] = true;
        $color_space = "";

        $auto = $img['auto'];
        $max_width = $img['target_width'];
        $max_height = $img['target_height'];
        $greyscale = $this->EE->TMPL->fetch_param('greyscale') == 'yes' ? true : false;

        if ($greyscale) {
            $color_space = "-greyscale";
        }

        // -------------------------------------
        // get the ratio needed
        // -------------------------------------
        $x_ratio = $max_width / $width;
        $y_ratio = $max_height / $height;

        // -------------------------------------
        // if image already meets criteria, load current values in
        // if not, use ratios to load new size info
        // -------------------------------------

        if (($width <= $max_width) && ($height <= $max_height)) {
            $img['out_width'] = $width;
            $img['out_height'] = $height;
        } else if (($x_ratio * $height) < $max_height) {
            $img['out_height'] = ceil($x_ratio * $height);
            $img['out_width'] = $max_width;
        } else {
            $img['out_width'] = ceil($y_ratio * $width);
            $img['out_height'] = $max_height;
        }

        // -------------------------------------
        //  Set image sizing Ratio
        //  Auto size Added By Erin Dalzell
        // -------------------------------------
        if ($auto) {
            if ($width > $height) {
                $img['out_width'] = $auto;
                $img['out_height'] = '0';
            } else {
                $img['out_height'] = $auto;
                $img['out_width'] = '0';
            }
        }

        // -------------------------------------
        // Do we want to Crop the image?
        // -------------------------------------
        if ($max_height != '9000' && $max_width != '9000' && $max_width != $max_height) {
            $img['crop'] = "yes";
            $img['proportional'] = false;
            $img['out_width'] = $max_width;
            $img['out_height'] = $max_height;
        }

        // -------------------------------------
        // Do we Need to crop?
        // -------------------------------------
        if ($max_width == $max_height && $auto == "") {
            $img['crop'] = "yes";
            $img['proportional'] = false;
            $img['out_width'] = $max_width;
            $img['out_height'] = $max_height;
        }

        $auto_height = $img['autoheight'];
        if ($auto_height) {
            $auto_height_factor = $auto_height / $height;
            $img['out_height'] = round($height * $auto_height_factor);
            $img['out_width'] = ($width * $auto_height_factor > $max_width) ? $max_width : round($width * $auto_height_factor);
            $img['crop'] = "yes";
            $img['proportional'] = false;
        }

        // set outputs
        $img['out_name'] = $img['base_filename'] . $color_space . '-' . $img['out_width'] . 'x' . $img['out_height'] . '.' . $img['extension'];
        $img['root_out_name'] = $img['cache_path'] . $img['out_name'];
        $img['browser_out_path'] = reduce_double_slashes("/" . str_replace($img['base_path'], '', $img['root_out_name']));

        return $img;
    }

    // -------------------------------------
    // checks cached images if they are present
    // and if they are older than the src img
    // -------------------------------------

    function check_some_cache($img) {

        $img['do_cache'] = "";

        $cache = (!$this->EE->TMPL->fetch_param('cache')) ? '' : $this->EE->TMPL->fetch_param('cache');

        $imageModified = @filemtime($img['base_path'] . $img['src']);
        $thumbModified = @filemtime($img['cache_path'] . $img['out_name']);

        // set a update flag
        if ($imageModified > $thumbModified || $cache == "no") {
            $img['do_cache'] = "update";
        }

        return $img;
    }

    // -------------------------------------
    // This function does the image resizing
    // -------------------------------------
    function do_some_image($img) {

        $file = $img['root_path'] . $img['basename'];
        $width = $img['out_width'];
        $height = $img['out_height'];
        $crop = $img['crop'];
        $proportional = $img['proportional'];
        $output = $img['cache_path'] . $img['out_name'];

        $quality = $img['quality'];
        $greyscale = $img['greyscale'];

        if ($height <= 0 && $width <= 0) {
            return false;
        }

        $info = getimagesize($file);
        $image = '';

        $final_width = 0;
        $final_height = 0;
        list($width_old, $height_old) = $info;

        if ($proportional) {
            if ($width == 0) {
                $factor = $height / $height_old;
            } elseif ($height == 0) {
                $factor = $width / $width_old;
            } else {
                $factor = min($width / $width_old, $height / $height_old);
            }

            $final_width = round($width_old * $factor);
            $final_height = round($height_old * $factor);

        } else {
            $final_width = ($width <= 0) ? $width_old : $width;
            $final_height = ($height <= 0) ? $height_old : $height;
        }

        if ($crop) {

            $int_width = 0;
            $int_height = 0;

            $adjusted_height = $final_height;
            $adjusted_width = $final_width;

            $wm = $width_old / $width;
            $hm = $height_old / $height;
            $h_height = $height / 2;
            $w_height = $width / 2;

            $ratio = $width / $height;
            $old_img_ratio = $width_old / $height_old;

            if ($old_img_ratio > $ratio) {
                $adjusted_width = $width_old / $hm;
                $half_width = $adjusted_width / 2;
                $int_width = $half_width - $w_height;
            } else if ($old_img_ratio <= $ratio) {
                $adjusted_height = $height_old / $wm;
                $half_height = $adjusted_height / 2;
                $int_height = $half_height - $h_height;
            }
        }

        if ($img['do_cache']) {
            $image_resized = imagecreatetruecolor($final_width, $final_height);
            
            switch (
                    $info[2]) {
                case IMAGETYPE_GIF:
                    $image = imagecreatefromgif($file);
                    break;
                case IMAGETYPE_JPEG:
                    imageinterlace($image_resized, true);
                    $image = imagecreatefromjpeg($file);
                    break;
                case IMAGETYPE_PNG:
                    $image = imagecreatefrompng($file);
                    break;
                default:
                    return false;
            }

            if (($info[2] == IMAGETYPE_GIF) || ($info[2] == IMAGETYPE_PNG)) {
                $trnprt_indx = imagecolortransparent($image);

                // If we have a specific transparent color
                if ($trnprt_indx >= 0) {

                    // Get the original image's transparent color's RGB values
                    $trnprt_color = imagecolorsforindex($image, $trnprt_indx);

                    // Allocate the same color in the new image resource
                    $trnprt_indx = imagecolorallocate($image_resized, $trnprt_color['red'], $trnprt_color['green'], $trnprt_color['blue']);

                    // Completely fill the background of the new image with allocated color.
                    imagefill($image_resized, 0, 0, $trnprt_indx);

                    // Set the background color for new image to transparent
                    imagecolortransparent($image_resized, $trnprt_indx);

                }
                // Always make a transparent background color for PNGs that don't have one allocated already
                elseif ($info[2] == IMAGETYPE_PNG) {

                    // Turn off transparency blending (temporarily)
                    imagealphablending($image_resized, false);

                    // Create a new transparent color for image
                    $color = imagecolorallocatealpha($image_resized, 0, 0, 0, 127);

                    // Completely fill the background of the new image with allocated color.
                    imagefill($image_resized, 0, 0, $color);

                    // Restore transparency blending
                    imagesavealpha($image_resized, true);
                }
            }

            if ($crop) {
                imagecopyresampled($image_resized, $image, -$int_width, -$int_height, 0, 0, $adjusted_width, $adjusted_height, $width_old, $height_old);
            } else {
                imagecopyresampled($image_resized, $image, 0, 0, 0, 0, $final_width, $final_height, $width_old, $height_old);
            }

            if ($greyscale) {
                for ($c = 0; $c < 256; $c++) {
                    $palette[$c] = imagecolorallocate($image_resized, $c, $c, $c);
                }

                for ($y = 0; $y < $final_height; $y++) {
                    for ($x = 0; $x < $final_width; $x++) {
                        $rgb = imagecolorat($image_resized, $x, $y);
                        $r = ($rgb >> 16) & 0xFF;
                        $g = ($rgb >> 8) & 0xFF;
                        $b = $rgb & 0xFF;
                        $gs = $this->yiq($r, $g, $b);
                        imagesetpixel($image_resized, $x, $y, $palette[$gs]);
                    }
                }
            }

            switch ($info[2]) {
                case IMAGETYPE_GIF:
                    imagegif($image_resized, $output);
                    break;
                case IMAGETYPE_JPEG:
                    imagejpeg($image_resized, $output, $quality);

                    break;
                case IMAGETYPE_PNG:
                    imagepng($image_resized, $output);
                    break;
                default:
                    return false;
            }
        }

        $img['out_width'] = $final_width;
        $img['out_height'] = $final_height;

        return $img;

    }

    function do_output($img) {

        /** -------------------------------------
        /*  is the tag in a pair if so do that
        /** -------------------------------------*/

        $tagdata = $this->EE->TMPL->tagdata;

        $tagdata = $this->EE->functions->prep_conditionals($tagdata, $this->EE->TMPL->var_single);

        if ($tagdata) {
            foreach ($this->EE->TMPL->var_single as $key => $val) {
                switch ($val) {
                    case "url":
                        $tagdata = $this->EE->TMPL->swap_var_single($val, $img['browser_out_path'], $tagdata);
                        break;
                    case "sized":
                        $tagdata = $this->EE->TMPL->swap_var_single($val, $img['browser_out_path'], $tagdata);
                        break;
                    case "width":
                    case "img_width":
                        $tagdata = $this->EE->TMPL->swap_var_single($val, $img['out_width'], $tagdata);
                        break;
                    case "height":
                        $tagdata = $this->EE->TMPL->swap_var_single($val, $img['out_height'], $tagdata);
                    case "img_height":
                        break;
                    case "src_width":
                        $tagdata = $this->EE->TMPL->swap_var_single($val, $img['src_width'], $tagdata);
                        break;
                    case "src_height":
                        $tagdata = $this->EE->TMPL->swap_var_single($val, $img['src_height'], $tagdata);
                        break;
                }
            }
            return $tagdata;
        } else {
    
            /** -------------------------------------
            /*  output just a simple img tag
            /** -------------------------------------*/
            return '<img src="' . $img['browser_out_path'] . '" width="' . $img['out_width'] . '" height="' . $img['out_height'] . '"' . $this->tag_vars . ' />';
            
        }

    }

    function do_output_svg($svgout) {
        $svgfilename = $svgout[0]['base_filename'].'.svg';
        $svgpath = $svgout[0]['cache_path'].$svgfilename;

        $out = "<svg xmlns=\"http://www.w3.org/2000/svg\" 
            viewBox=\"0 0 ".$svgout[count($svgout)-1]['out_width']." ".$svgout[count($svgout)-1]['out_height']."\" 
            preserveAspectRatio=\"xMidYMid meet\">
            <title>Clown Car Technique</title>
                <style>
                    svg {
                        background-size: 100% 100%;
                        background-repeat: no-repeat;
                        background-image: url(".$svgout[0]['out_name'].");
                        height: auto;
                    }
            ";

            $w = '';
            foreach($svgout as $svg) {
                if ($w == '') {
                    $w = $svgout[0]['target_width']+1;
                    continue;
                }
                $out .= "
                @media (min-width: ".$w."px) {
                    svg {background-image: url(".$svg['out_name'].");}
                }
                ";
                $w = $svg['target_width']+1;
            }

            $out.= "</style></svg>";

        file_put_contents($svgpath, $out);
        
        $outimg = array(
            'browser_out_path' => reduce_double_slashes("/" . str_replace($svgout[0]['base_path'], '', $svgout[0]['cache_path'])) . $svgfilename,
            'out_width' => $svgout[count($svgout)-1]['out_width'],
            'out_height'    => $svgout[count($svgout)-1]['out_height'],
            'src_width' => $svgout[count($svgout)-1]['src_width'],
            'src_height'    => $svgout[count($svgout)-1]['src_height'],
        );

        return '<object data="' . $outimg['browser_out_path'] . '" 
            width="' . $outimg['out_width'] . '" 
            height="' . $outimg['out_height'] . '"
            '.$this->tag_vars.'></object>';
    }

    // -------------------------------------
    // This function does remote image fetch
    // -------------------------------------
    function do_remote($img) {

        $refresh = (!$this->EE->TMPL->fetch_param('refresh')) ? '1440' : $this->EE->TMPL->fetch_param('refresh');

        $remote_user = (!$this->EE->TMPL->fetch_param('remote_user')) ? '' : $this->EE->TMPL->fetch_param('remote_user');
        $remote_pass = (!$this->EE->TMPL->fetch_param('remote_pass')) ? '' : $this->EE->TMPL->fetch_param('remote_pass');

        $url_filename = parse_url($img['url_src']);
        $url_filename = pathinfo($url_filename['path']);

        $base_cache = reduce_double_slashes($img['base_path'] . "/images/sized/");
        $base_cache = (!$this->EE->TMPL->fetch_param('base_cache')) ? $base_cache : $this->EE->TMPL->fetch_param('base_cache');
        $base_cache = reduce_double_slashes($base_cache);
        $save_mask = str_replace('/', '-', $url_filename['dirname']);
        $save_name = $img['url_host_cache_dir'] . $save_mask . "-" . $url_filename['basename'];

        $save_root_folder = $base_cache . "/remote";
        $save_root_path = $save_root_folder . "/" . $save_name;
        $save_rel_path = $base_cache . "/remote/" . $save_name;
        $save_rel_path = "/" . str_replace($img['base_path'], '', $save_rel_path);
        $save_rel_path = reduce_double_slashes($save_rel_path);

        $save_dir = reduce_double_slashes($base_cache . "/remote/");

        if (!is_dir($save_dir)) {
            // make the directory if we can
            if (!mkdir($save_dir, 0777, true)) {
                $this->EE->TMPL->log_item("Error: could not create cache directory! Please manually create the cache directory " . $save_dir . "/remote/ with 777 permissions");
                return $this->EE->TMPL->no_results();
            }
        }

        $remote_diff = $refresh + 1;
        if (file_exists($save_root_path)) {
            $remote_diff = round((time() - filectime($save_root_path)) / 60) - 9;
        }

        if ($remote_diff > $refresh) {

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $img['url_src']);
            if ($remote_pass) {
                curl_setopt($ch, CURLOPT_USERPWD, $remote_user . ":" . $remote_pass);
            }
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            $file_contents = curl_exec($ch);
            $curl_info = curl_getinfo($ch);

            if (curl_errno($ch)) {
                return;
            } else {
                curl_close($ch);
            }

            $this->file_put_contents_atomic($save_root_path, $file_contents, $save_dir, 0777);

            $this->EE->TMPL->log_item("imgsizer.remote.did_fetch: yes");

        }

        /*debug*/
        $this->EE->TMPL->log_item("imgsizer.remote.save_src_path: " . $save_rel_path);
        return $save_rel_path;

    }

    function file_put_contents_atomic($filename, $content, $temp_path, $file_mode) {

        $temp = tempnam($temp_path, 'temp');
        if (!($f = @fopen($temp, 'wb'))) {
            $temp = $temp_path . DIRECTORY_SEPARATOR . uniqid('temp');
            if (!($f = @fopen($temp, 'wb'))) {
                trigger_error("file_put_contents_atomic() : error writing temporary file '$temp'", E_USER_WARNING);
                return false;
            }
        }

        fwrite($f, $content);
        fclose($f);

        if (!@rename($temp, $filename)) {
            @unlink($filename);
            @rename($temp, $filename);
        }

        @chmod($filename, $file_mode);

        return true;

    }

    //Creates yiq function
    function yiq($r, $g, $b) {
        return (($r * 0.299) + ($g * 0.587) + ($b * 0.114));
    }

    // --------------------------------------------------------------------

    /**
     * Usage
     *
     * This function describes how the plugin is used.
     *
     * @access  public
     * @return  string
     */

    //  Make sure and use output buffering

    static function usage() {

        return file_get_contents(__DIR__.'/README.md');

    }
    // END

}
/* End of file pi.imgsizer.php */
