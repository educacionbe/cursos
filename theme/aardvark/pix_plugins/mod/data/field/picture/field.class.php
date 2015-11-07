<?php // $Id: field.class.php,v 1.15 2006/04/10 16:46:52 moodler Exp $
///////////////////////////////////////////////////////////////////////////
//                                                                       //
// NOTICE OF COPYRIGHT                                                   //
//                                                                       //
// Moodle - Modular Object-Oriented Dynamic Learning Environment         //
//          http://moodle.org                                            //
//                                                                       //
// Copyright (C) 1999-onwards Moodle Pty Ltd  http://moodle.com          //
//                                                                       //
// This program is free software; you can redistribute it and/or modify  //
// it under the terms of the GNU General Public License as published by  //
// the Free Software Foundation; either version 2 of the License, or     //
// (at your option) any later version.                                   //
//                                                                       //
// This program is distributed in the hope that it will be useful,       //
// but WITHOUT ANY WARRANTY; without even the implied warranty of        //
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the         //
// GNU General Public License for more details:                          //
//                                                                       //
//          http://www.gnu.org/copyleft/gpl.html                         //
//                                                                       //
///////////////////////////////////////////////////////////////////////////

require_once($CFG->dirroot.'/mod/data/field/file/field.class.php'); // Base class is 'file'

class data_field_picture extends data_field_file { 

    var $type = 'picture';

    var $previewwidth  = 50;
    var $previewheight = 50;

    function data_field_picture($field=0, $data=0) {
        parent::data_field_base($field, $data);
    }

    function display_add_field($recordid=0){
        global $CFG;

        $filepath = '';
        $filename = '';
        $description = '';

        if ($recordid){
            if ($content = get_record('data_content', 'fieldid', $this->field->id, 'recordid', $recordid)) {
                $filename = $content->content;
                $description = $content->content1;
            }

            $path = $this->data->course.'/'.$CFG->moddata.'/data/'.$this->data->id.'/'.$this->field->id.'/'.$recordid;

            if ($CFG->slasharguments) {
                $filepath = $CFG->wwwroot.'/file.php/'.$path.'/'.$filename;
            } else {
                $filepath = $CFG->wwwroot.'/file.php?file=/'.$path.'/'.$filename;
            }
        }

        $str = '<div title="'.$this->field->description.'">';
        $str .= '<input type="hidden" name ="field_'.$this->field->id.'_file" id="field_'.$this->field->id.'_file"  value="fakevalue" />';
        $str .= get_string('picture','data'). ': <input type="file" name ="field_'.$this->field->id.'" id="field_'.$this->field->id.'" /><br />';
        $str .= get_string('optionaldescription','data') .': <input type="text" name="field_'
                .$this->field->id.'_filename" id="field_'.$this->field->id.'_filename" value="'.$description.'" /><br />';
        $str .= '<input type="hidden" name="MAX_FILE_SIZE" value="'.$this->field->param3.'" />';
        if ($filepath){
            $str .= '<img width="'.$this->previewwidth.'" height="'.$this->previewheight.'" src="'.$filepath.'" />';
        }
        $str .= '</div>';
        return $str;
    }

    function display_browse_field($recordid, $template) {
        global $CFG;
        
        if ($content = get_record('data_content', 'fieldid', $this->field->id, 'recordid', $recordid)){
            if (isset($content->content)){
                $contents[0] = $content->content;
                $contents[1] = $content->content1;
            }

            if (empty($contents[0])) {   // Nothing to show
                return '';
            }

            $alt = empty($contents[1])? '':$contents[1];
            $title = empty($contents[1])? '':$contents[1];
            $src = $contents[0];

            $path = $this->data->course.'/'.$CFG->moddata.'/data/'.$this->data->id.'/'.$this->field->id.'/'.$recordid;
            $thumbnaillocation = $CFG->dataroot .'/'.$this->data->course.'/'.$CFG->moddata.'/data/'.$this->data->id.'/'.$this->field->id.'/'.$recordid.'/thumb/'.$src;

            if ($CFG->slasharguments) {
                $source = $CFG->wwwroot.'/file.php/'.$path.'/'.$src;
                $thumbnailsource = file_exists($thumbnaillocation) ? $CFG->wwwroot.'/file.php/'.$path.'/thumb/'.$src : $source;
            } else {
                $source = $CFG->wwwroot.'/file.php?file=/'.$path.'/'.$src;
                $thumbnailsource = file_exists($thumbnaillocation) ? $CFG->wwwroot.'/file.php?file=/'.$path.'/thumb/'.$src : $source;
            }

            if ($template == 'listtemplate') {
                $width = $this->field->param4 ? ' width="'.$this->field->param4.'" ' : ' ';
                $height = $this->field->param5 ? ' height="'.$this->field->param5.'" ' : ' ';
                $str = '<a href="view.php?d='.$this->field->dataid.'&amp;rid='.$recordid.'"><img '.
                     $width.$height.' src="'.$thumbnailsource.'" alt="'.$alt.'" title="'.$title.'" border="0" /></a>';
            } else {
                $width = $this->field->param1 ? ' width="'.$this->field->param1.'" ':' ';
                $height = $this->field->param2 ? ' height="'.$this->field->param2.'" ':' ';
                $str = '<a href="'.$source.'"><img '.$width.$height.' src="'.$source.'" alt="'.$alt.'" title="'.$title.'" border="0"/></a>';
            }
            return $str;
        }
        return false;
    }

    function update_field() {

        // Get the old field data so that we can check whether the thumbnail dimensions have changed
        $oldfield = get_record('data_fields', 'id', $this->field->id);

        if (!update_record('data_fields', $this->field)) {
            notify('updating of new field failed!');
            return false;
        }

        // Have the thumbnail dimensions changed?
        if ($oldfield && ($oldfield->param4 != $this->field->param4 || $oldfield->param5 != $this->field->param5)) {

            // Check through all existing records and update the thumbnail
            if ($contents = get_records('data_content', 'fieldid', $this->field->id)) { 
                if (count($contents) > 20) {
                    notify(get_string('resizingimages', 'data'), 'notifysuccess');
                    echo "\n\n";   // To make sure that ob_flush() has the desired effect
                    ob_flush();
                }
                foreach ($contents as $content) {
                    @set_time_limit(300);     // Might be slow!
                    $this->update_thumbnail($content);
                }
            }
        }
        return true;
    }

    function update_content($recordid, $value, $name) {
        parent::update_content($recordid, $value, $name);

        $content = get_record('data_content','fieldid', $this->field->id, 'recordid', $recordid);

        $this->update_thumbnail($content); // Regenerate the thumbnail
    }

    /**
    * (Re)generate thumbnail image according to the dimensions specified in the field settings.
    * If thumbnail width and height are BOTH not specified then no thumbnail is generated, and
    * additionally an attempted delete of the existing thumbnail takes place.
    */
    function update_thumbnail($content) {
        global $CFG;

        require_once($CFG->libdir . '/gdlib.php');

        $datalocation = $CFG->dataroot .'/'.$this->data->course.'/'.$CFG->moddata.'/data/'.
                        $this->data->id.'/'.$this->field->id.'/'.$content->recordid;
        $originalfile = $datalocation.'/'.$content->content;
        if (!file_exists($originalfile)) {
            return;
        }
        if (!file_exists($datalocation.'/thumb')) {
             mkdir($datalocation.'/thumb', 0777);
        }
        $thumbnaillocation = $datalocation.'/thumb/'.$content->content;
        $imageinfo = GetImageSize($originalfile);
        $image->width  = $imageinfo[0];
        $image->height = $imageinfo[1];
        $image->type   = $imageinfo[2];

        if (!$image->width || !$image->height) {  // Should not happen
            return;
        }

        switch ($image->type) {
            case 1: 
                if (function_exists('ImageCreateFromGIF')) {
                    $im = ImageCreateFromGIF($originalfile); 
                } else {
                    return;
                }
                break;
            case 2: 
                if (function_exists('ImageCreateFromJPEG')) {
                    $im = ImageCreateFromJPEG($originalfile); 
                } else {
                    return;
                }
                break;
            case 3:
                if (function_exists('ImageCreateFromPNG')) {
                    $im = ImageCreateFromPNG($originalfile); 
                } else {
                    return;
                }
                break;
        }

        
        $thumbwidth  = $this->field->param4;
        $thumbheight = $this->field->param5;
        
        if ($thumbwidth || $thumbheight) { // Only if either width OR height specified do we want a thumbnail
            
            $wcrop = $image->width;
            $hcrop = $image->height;
            
            if ($thumbwidth && !$thumbheight) {
                $thumbheight = $image->height * $thumbwidth / $image->width;
            } else if($thumbheight && !$thumbwidth) {
                $thumbheight = $image->width * $thumbheight / $image->height;
            } else { // BOTH are set - may need to crop if aspect ratio differs
                $hratio = $image->height / $thumbheight;
                $wratio = $image->width  / $thumbwidth;
                if ($wratio > $hratio) { // Crop the width
                    $wcrop = intval($thumbwidth * $hratio);
                } elseif($hratio > $wratio) { // Crop the height
                    $hcrop = intval($thumbheight * $wratio);
                }
            }
            
            // At this point both $thumbwidth and $thumbheight are set, and $wcrop and $hcrop
            if (function_exists('ImageCreateTrueColor') and $CFG->gdversion >= 2) {
                $im1 = ImageCreateTrueColor($thumbwidth,$thumbheight);
            } else {
                $im1 = ImageCreate($thumbwidth,$thumbheight);
            }
            $cx = $image->width  / 2;
            $cy = $image->height / 2;
    
            // These "half" measurements use the "crop" values rather than the actual dimensions
            $halfwidth  = floor($wcrop * 0.5);
            $halfheight = floor($hcrop * 0.5);
            
            ImageCopyBicubic($im1, $im, 0, 0, $cx-$halfwidth, $cy-$halfheight, 
                             $thumbwidth, $thumbheight, $halfwidth*2, $halfheight*2);
    
            if (function_exists('ImageJpeg')) {
                @touch($thumbnaillocation);  // Helps in Safe mode
                if (ImageJpeg($im1, $thumbnaillocation, 90)) {
                    @chmod($thumbnaillocation, 0666);
                }
            }
            
        } else { // Try and remove the thumbnail - we don't want thumbnailing active
            @unlink($thumbnaillocation);
        }
    }
}

?>
