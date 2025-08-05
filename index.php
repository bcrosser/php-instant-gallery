<?php
// Configuration
$base_dir = './pics';  // Base directory containing media files
$thumbs_dir = './thumbs';  // Directory for generated thumbnails
$thumb_width = 200;       // default thumbnail size
$show_gps_coords = false;        // Show GPS coordinates in image details

// FFmpeg/FFprobe executable paths - set these if not in system PATH
$ffmpeg_path = '';  // e.g., 'C:\\ffmpeg\\bin\\ffmpeg.exe' or '/usr/local/bin/ffmpeg' or leave empty for system PATH
$ffprobe_path = ''; // e.g., 'C:\\ffmpeg\\bin\\ffprobe.exe' or '/usr/local/bin/ffprobe' or leave empty for system PATH

// Directory navigation links - add your custom links here
$nav_links = [
    // Format: 'Display Name' => 'path/to/directory'
    // Add more directory links as needed
];

// External links - these will open directly in the browser without processing
$external_links = [
    'Google' => 'https://google.com',
    // Add more links as needed
];

// Constants and configuration arrays
class GalleryConfig {
    // Supported file extensions
    const SUPPORTED_EXTENSIONS = '/\.(jpg|jpeg|gif|png|webp|bmp|ico|apng|svg|pdf|mp4|webm|ogg|ogv|3gp|qt|mov|qtff|avi|mp3|opus|wav|flac|aac)$/i';
    const IMAGE_EXTENSIONS = '/\.(jpg|gif|jpeg|png|webp|bmp|ico|apng|svg|pdf)$/i';
    const VIDEO_EXTENSIONS = '/\.(mp4|webm|ogg|ogv|3gp|qt|mov|qtff|avi)$/i';
    const AUDIO_EXTENSIONS = '/\.(mp3|opus|wav|flac|aac)$/i';
    
    // Video utilities
    public static function isVideo($filename) {
        return preg_match(self::VIDEO_EXTENSIONS, $filename);
    }
    
    public static function isImage($filename) {
        return preg_match(self::IMAGE_EXTENSIONS, $filename);
    }
    
    public static function isAudio($filename) {
        return preg_match(self::AUDIO_EXTENSIONS, $filename);
    }
    
    public static function isPDF($filename) {
        return preg_match('/\.pdf$/i', $filename);
    }
    
    public static function getImageMimeType($filename) {
        if (preg_match('/\.(jpg|jpeg)$/i', $filename)) {
            return 'image/jpeg';
        } else if (preg_match('/\.png$/i', $filename)) {
            return 'image/png';
        } else if (preg_match('/\.gif$/i', $filename)) {
            return 'image/gif';
        } else if (preg_match('/\.webp$/i', $filename)) {
            return 'image/webp';
        } else if (preg_match('/\.bmp$/i', $filename)) {
            return 'image/bmp';
        } else if (preg_match('/\.ico$/i', $filename)) {
            return 'image/x-icon';
        } else if (preg_match('/\.apng$/i', $filename)) {
            return 'image/apng';
        } else if (preg_match('/\.svg$/i', $filename)) {
            return 'image/svg+xml';
        } else if (preg_match('/\.pdf$/i', $filename)) {
            return 'application/pdf';
        } else {
            return 'image/jpeg'; // Default fallback
        }
    }
    
    public static function getVideoMimeType($filename) {
        if (preg_match('/\.mp4$/i', $filename)) {
            return 'video/mp4';
        } else if (preg_match('/\.webm$/i', $filename)) {
            return 'video/webm';
        } else if (preg_match('/\.(ogg|ogv)$/i', $filename)) {
            return 'video/ogg';
        } else if (preg_match('/\.3gp$/i', $filename)) {
            return 'video/3gpp';
        } else if (preg_match('/\.(qt|mov|qtff)$/i', $filename)) {
            return 'video/quicktime';
        } else if (preg_match('/\.avi$/i', $filename)) {
            return 'video/x-msvideo';
        } else {
            return 'video/mp4'; // Default fallback
        }
    }
    
    public static function getAudioMimeType($filename) {
        if (preg_match('/\.mp3$/i', $filename)) {
            return 'audio/mpeg';
        } else if (preg_match('/\.opus$/i', $filename)) {
            return 'audio/opus';
        } else if (preg_match('/\.wav$/i', $filename)) {
            return 'audio/wav';
        } else if (preg_match('/\.flac$/i', $filename)) {
            return 'audio/flac';
        } else if (preg_match('/\.aac$/i', $filename)) {
            return 'audio/aac';
        } else {
            return 'audio/mpeg'; // Default fallback
        }
    }
    
    public static function getVideoThumbnailPath($video_path, $thumbs_dir) {
        return $thumbs_dir . '/' . basename($video_path) . '.jpg';
    }
    
    public static function getImageThumbnailPath($image_path, $thumbs_dir) {
        return $thumbs_dir . '/' . basename($image_path) . '.jpg';
    }
    
    public static function getAudioThumbnailPath($audio_path, $thumbs_dir) {
        return $thumbs_dir . '/' . basename($audio_path) . '.jpg';
    }
    
    public static function createImageThumbnail($source, $destination, $width = 200) {
        $filename = basename($source);
        
        // Handle PDF files specially
        if (self::isPDF($source)) {
            return self::createPDFThumbnail($source, $destination, $width);
        }
        
        // Handle SVG files specially
        if (preg_match('/\.svg$/i', $filename)) {
            return self::createSVGThumbnail($source, $destination, $width);
        }
        
        // Try to get image dimensions for supported formats
        $dims = @getimagesize($source);
        if (!$dims) {
            error_log("Could not get image dimensions for: " . $source);
            return false;
        }
        
        $w = $dims[0];
        $h = $dims[1];
        
        // Calculate new dimensions with preserved aspect ratio
        $ratio = $w / $h;
        if ($ratio < 1) { // Portrait
            $new_width = $width * $ratio;
            $new_height = $width;
        } else {         // Landscape
            $new_width = $width;
            $new_height = $width / $ratio;
        }
        
        // Create blank canvas
        $canvas = imagecreatetruecolor($new_width, $new_height);
        
        // Load original image based on type
        $img = null;
        $image_type = $dims[2];
        
        switch ($image_type) {
            case IMAGETYPE_JPEG:
                $img = @imagecreatefromjpeg($source);
                break;
            case IMAGETYPE_PNG:
                $img = @imagecreatefrompng($source);
                // Preserve transparency
                imagealphablending($canvas, false);
                imagesavealpha($canvas, true);
                $transparent = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
                imagefill($canvas, 0, 0, $transparent);
                break;
            case IMAGETYPE_GIF:
                $img = @imagecreatefromgif($source);
                break;
            case IMAGETYPE_WEBP:
                if (function_exists('imagecreatefromwebp')) {
                    $img = @imagecreatefromwebp($source);
                }
                break;
            case IMAGETYPE_BMP:
                if (function_exists('imagecreatefrombmp')) {
                    $img = @imagecreatefrombmp($source);
                }
                break;
            case IMAGETYPE_ICO:
                // ICO files are complex, try basic approach
                $img = @imagecreatefromstring(file_get_contents($source));
                break;
            default:
                // Try generic approach
                $img = @imagecreatefromstring(file_get_contents($source));
                break;
        }
        
        if (!$img) {
            error_log("Could not create image resource for: " . $source);
            return false;
        }
        
        // Copy original image to canvas
        imagecopyresampled($canvas, $img, 0, 0, 0, 0,
                          $new_width, $new_height, $w, $h);
        
        // Save thumbnail
        $success = imagejpeg($canvas, $destination, 85); // Quality: 85%
        imagedestroy($canvas);
        imagedestroy($img);
        
        return $success;
    }
    
    public static function createPDFThumbnail($source, $destination, $width = 200) {
        // For PDFs, we'll use PDF.js on the client side to generate thumbnails
        // This function now just creates a temporary placeholder that will be replaced
        // by actual PDF page thumbnails generated in the browser
        
        // Create a simple placeholder that indicates PDF.js will handle the real thumbnail
        $img = imagecreatetruecolor($width, $width);
        
        // PDF.js loading colors
        $bg_color = imagecolorallocate($img, 248, 249, 250);
        $border_color = imagecolorallocate($img, 52, 144, 220);
        $text_color = imagecolorallocate($img, 52, 144, 220);
        $loading_color = imagecolorallocate($img, 108, 117, 125);
        
        // Fill background
        imagefill($img, 0, 0, $bg_color);
        
        // Draw border
        imagerectangle($img, 0, 0, $width-1, $width-1, $border_color);
        
        // Add PDF.js loading indicator
        $text = "PDF";
        $font_size = 5;
        $text_width = strlen($text) * imagefontwidth($font_size);
        $text_x = ($width - $text_width) / 2;
        $text_y = ($width / 2) - 20;
        imagestring($img, $font_size, $text_x, $text_y, $text, $text_color);
        
        // Add loading text
        $loading_text = "Loading...";
        $loading_font = 2;
        $loading_width = strlen($loading_text) * imagefontwidth($loading_font);
        $loading_x = ($width - $loading_width) / 2;
        $loading_y = ($width / 2) + 10;
        imagestring($img, $loading_font, $loading_x, $loading_y, $loading_text, $loading_color);
        
        $success = imagejpeg($img, $destination, 85);
        imagedestroy($img);
        
        error_log("PDF placeholder created for PDF.js processing: " . $destination);
        return $success;
    }
    
    public static function getPDFInfo($source) {
        // Try to get PDF page count and other info
        $info = array(
            'pages' => 1,
            'title' => basename($source),
            'size' => filesize($source)
        );
        
        // Try to get page count using Imagick
        if (class_exists('Imagick')) {
            try {
                $imagick = new Imagick();
                $imagick->pingImage($source);
                $info['pages'] = $imagick->getNumberImages();
                $imagick->clear();
                $imagick->destroy();
                error_log("PDF page count detected via Imagick: " . $info['pages']);
            } catch (Exception $e) {
                error_log("Failed to get PDF page count via Imagick: " . $e->getMessage());
            }
        }
        
        // Fallback: try to parse PDF manually for page count
        if ($info['pages'] == 1) {
            try {
                $content = file_get_contents($source);
                if ($content !== false) {
                    // Look for /Count in the PDF structure
                    if (preg_match('/\/Count\s+(\d+)/', $content, $matches)) {
                        $info['pages'] = (int)$matches[1];
                        error_log("PDF page count detected via manual parsing: " . $info['pages']);
                    }
                    // Alternative: count page objects
                    elseif (preg_match_all('/\/Type\s*\/Page[^s]/', $content, $matches)) {
                        $info['pages'] = count($matches[0]);
                        error_log("PDF page count detected via page object counting: " . $info['pages']);
                    }
                }
            } catch (Exception $e) {
                error_log("Failed to manually parse PDF: " . $e->getMessage());
            }
        }
        
        return $info;
    }
    
    public static function createSVGThumbnail($source, $destination, $width = 200) {
        // Try ImageMagick for SVG (best quality)
        if (class_exists('Imagick')) {
            try {
                $imagick = new Imagick();
                $imagick->setBackgroundColor(new ImagickPixel('white'));
                $imagick->readImage($source);
                $imagick->setImageFormat('jpeg');
                $imagick->setImageCompressionQuality(85);
                
                // Resize maintaining aspect ratio
                $imagick->thumbnailImage($width, $width, true);
                
                $success = $imagick->writeImage($destination);
                $imagick->clear();
                $imagick->destroy();
                
                if ($success) {
                    error_log("SVG thumbnail created successfully with Imagick: " . $destination);
                    return true;
                }
            } catch (Exception $e) {
                error_log("Imagick SVG processing failed: " . $e->getMessage());
            }
        }
        
        // Fallback: create a simple placeholder for SVG
        $img = imagecreatetruecolor($width, $width);
        $bg_color = imagecolorallocate($img, 250, 250, 250);
        $text_color = imagecolorallocate($img, 80, 150, 80);
        $border_color = imagecolorallocate($img, 180, 220, 180);
        
        // Fill background
        imagefill($img, 0, 0, $bg_color);
        
        // Draw border
        imagerectangle($img, 0, 0, $width-1, $width-1, $border_color);
        
        // Add SVG text
        $text = "SVG";
        $text_x = ($width - strlen($text) * 10) / 2;
        $text_y = $width / 2 - 10;
        imagestring($img, 4, $text_x, $text_y, $text, $text_color);
        
        $success = imagejpeg($img, $destination, 85);
        imagedestroy($img);
        
        error_log("SVG placeholder thumbnail created: " . $destination);
        return $success;
    }
    
    public static function createVideoThumbnail($source, $destination) {
        global $thumb_width, $ffmpeg_path;
        
        // Determine ffmpeg executable path
        $ffmpeg_cmd = !empty($ffmpeg_path) ? $ffmpeg_path : 'ffmpeg';
        
        // Get video metadata to determine aspect ratio
        $video_metadata = self::getVideoMetadata($source);
        $width = $thumb_width;
        $height = $thumb_width; // Default to square
        
        // Extract video dimensions from metadata if available
        if ($video_metadata && isset($video_metadata['metadata'])) {
            $metadata = $video_metadata['metadata'];
            if (isset($metadata['Width']) && isset($metadata['Height'])) {
                // Parse width and height from metadata (format: "1280 pixels")
                $video_width = (int)str_replace(' pixels', '', $metadata['Width']);
                $video_height = (int)str_replace(' pixels', '', $metadata['Height']);
                
                if ($video_width > 0 && $video_height > 0) {
                    // Calculate proportional height based on video aspect ratio
                    $aspect_ratio = $video_width / $video_height;
                    
                    if ($aspect_ratio > 1) {
                        // Landscape: keep width, adjust height
                        $width = $thumb_width;
                        $height = round($thumb_width / $aspect_ratio);
                    } else {
                        // Portrait: keep height, adjust width
                        $height = $thumb_width;
                        $width = round($thumb_width * $aspect_ratio);
                    }
                    
                    error_log("Video dimensions: {$video_width}x{$video_height}, aspect ratio: {$aspect_ratio}, thumbnail: {$width}x{$height}");
                }
            }
        }
        
        // Extract a single frame at 1 second mark and save as JPEG
        $command = "\"{$ffmpeg_cmd}\" -i \"{$source}\" -ss 00:00:01 -vframes 1 -s {$width}x{$height} -f image2 \"{$destination}\"";
        error_log("Running ffmpeg command: " . $command);
        exec($command, $output, $return_code);
        error_log("FFmpeg return code: " . $return_code . ", output: " . implode("\n", $output));
        
        // Check if thumbnail was created, if not try again with different timestamp
        if (!file_exists($destination) || filesize($destination) == 0) {
            $command = "\"{$ffmpeg_cmd}\" -i \"{$source}\" -ss 00:00:10 -vframes 1 -s {$width}x{$height} -f image2 \"{$destination}\"";
            error_log("Retry ffmpeg command: " . $command);
            exec($command, $output2, $return_code2);
            error_log("FFmpeg retry return code: " . $return_code2 . ", output: " . implode("\n", $output2));
        }
        
        // If still fails, create an empty placeholder
        if (!file_exists($destination) || filesize($destination) == 0) {
            // Create a black placeholder with text maintaining aspect ratio
            $img = imagecreatetruecolor($width, $height);
            $text_color = imagecolorallocate($img, 255, 255, 255);
            $bg_color = imagecolorallocate($img, 0, 0, 0);
            imagefilledrectangle($img, 0, 0, $width, $height, $bg_color);
            
            // Center the "Video" text
            $text_x = max(0, ($width - 40) / 2);  // Approximate text width
            $text_y = max(0, $height / 2);
            imagestring($img, 3, $text_x, $text_y, "Video", $text_color);
            
            imagejpeg($img, $destination, 90);
            imagedestroy($img);
        }
    }
    
    public static function createAudioThumbnail($source, $destination, $width = 200) {
        global $ffmpeg_path;
        
        // First, try to extract embedded album art using FFmpeg
        $ffmpeg_cmd = !empty($ffmpeg_path) ? $ffmpeg_path : 'ffmpeg';
        $temp_art = $destination . '.temp.jpg';
        
        // Try to extract album art
        $command = "\"{$ffmpeg_cmd}\" -i \"{$source}\" -an -vcodec copy \"{$temp_art}\" 2>/dev/null";
        exec($command, $output, $return_code);
        
        // If album art was extracted successfully, resize it and use it
        if (file_exists($temp_art) && filesize($temp_art) > 0) {
            // Resize the extracted album art to thumbnail size
            $success = self::createImageThumbnail($temp_art, $destination, $width);
            unlink($temp_art); // Clean up temp file
            
            if ($success) {
                error_log("Audio thumbnail created from embedded album art: " . $destination);
                return true;
            }
        }
        
        // If no album art found or extraction failed, create a preset thumbnail
        return self::createPresetAudioThumbnail($source, $destination, $width);
    }
    
    private static function createPresetAudioThumbnail($source, $destination, $width = 200) {
        // Create an attractive audio thumbnail with waveform-like design
        $height = $width; // Square thumbnail
        
        $img = imagecreatetruecolor($width, $height);
        
        // Audio player colors
        $bg_color = imagecolorallocate($img, 45, 47, 54);        // Dark background
        $accent_color = imagecolorallocate($img, 29, 185, 84);   // Spotify green
        $text_color = imagecolorallocate($img, 255, 255, 255);   // White text
        $wave_color = imagecolorallocate($img, 29, 185, 84);     // Green waves
        $dim_wave_color = imagecolorallocate($img, 83, 83, 91);  // Dimmed waves
        
        // Fill background
        imagefill($img, 0, 0, $bg_color);
        
        // Draw border
        imagerectangle($img, 0, 0, $width-1, $height-1, $accent_color);
        imagerectangle($img, 1, 1, $width-2, $height-2, $accent_color);
        
        // Draw waveform visualization
        $center_y = $height / 2;
        $wave_width = 3;
        $wave_spacing = 5;
        $num_waves = floor(($width - 40) / $wave_spacing);
        $start_x = ($width - ($num_waves * $wave_spacing)) / 2;
        
        for ($i = 0; $i < $num_waves; $i++) {
            $x = $start_x + ($i * $wave_spacing);
            
            // Create pseudo-random wave heights for visual effect
            $seed = ($i * 7 + 13) % 17; // Simple pseudo-random
            $wave_height = 10 + ($seed * 3);
            
            // Use accent color for some waves, dimmed for others
            $color = ($i % 3 == 0) ? $wave_color : $dim_wave_color;
            
            // Draw vertical wave bar
            imagefilledrectangle($img, $x, $center_y - $wave_height/2, 
                               $x + $wave_width, $center_y + $wave_height/2, $color);
        }
        
        // Draw music icon in the center
        $icon_x = $width / 2;
        $icon_y = $height / 2;
        
        // Draw a simple but recognizable musical note using basic shapes
        // Main note head (filled circle)
        imagefilledellipse($img, $icon_x, $icon_y + 5, 16, 12, $text_color);
        
        // Note stem (vertical line)
        imagefilledrectangle($img, $icon_x + 6, $icon_y - 20, $icon_x + 9, $icon_y + 5, $text_color);
        
        // Musical note flag (curved shape approximated with rectangles)
        imagefilledrectangle($img, $icon_x + 9, $icon_y - 20, $icon_x + 20, $icon_y - 15, $text_color);
        imagefilledrectangle($img, $icon_x + 15, $icon_y - 15, $icon_x + 22, $icon_y - 8, $text_color);
        
        // Get file extension for format label
        $extension = strtoupper(pathinfo($source, PATHINFO_EXTENSION));
        $format_text = $extension;
        $format_font = 2;
        $format_width = strlen($format_text) * imagefontwidth($format_font);
        $format_x = ($width - $format_width) / 2;
        $format_y = $height - 25;
        imagestring($img, $format_font, $format_x, $format_y, $format_text, $text_color);
        
        $success = imagejpeg($img, $destination, 85);
        imagedestroy($img);
        
        error_log("Audio preset thumbnail created: " . $destination);
        return $success;
    }
    
    public static function getVideoCreationDate($video_path) {
        $metadata = self::getVideoMetadata($video_path);
        
        if ($metadata && isset($metadata['creation_date'])) {
            return $metadata['creation_date'];
        }
        
        // If no valid date found in metadata, fall back to file modification time
        return filemtime($video_path);
    }
    
    public static function getVideoMetadata($video_path) {
        global $show_gps_coords, $ffprobe_path;
        
        // Determine ffprobe executable path
        $ffprobe_cmd = !empty($ffprobe_path) ? $ffprobe_path : 'ffprobe';
        
        // Get both format and stream metadata
        $command = "\"{$ffprobe_cmd}\" -v quiet -print_format json -show_format -show_streams \"{$video_path}\"";
        error_log("Running ffprobe command: " . $command);
        $output = shell_exec($command);
        error_log("FFprobe output length: " . strlen($output) . " characters");
        
        if (empty($output)) {
            error_log("FFprobe returned empty output for: " . $video_path);
            return null;
        }
        
        $metadata = json_decode($output, true);
        
        if (empty($metadata)) {
            error_log("Failed to decode JSON from ffprobe output for: " . $video_path);
            error_log("Raw output: " . $output);
            return null;
        }
        
        error_log("Successfully decoded ffprobe metadata for: " . $video_path);
        
        $extracted_metadata = array();
        $creation_date = null;
        
        // Extract format metadata (container level)
        if (isset($metadata['format']) && isset($metadata['format']['tags'])) {
            $tags = $metadata['format']['tags'];
            
            // Try different possible tag names for creation date
            $date_keys = ['creation_time', 'date', 'DateTimeOriginal', 'com.apple.quicktime.creationdate'];
            
            foreach ($date_keys as $key) {
                if (isset($tags[$key])) {
                    $date = strtotime($tags[$key]);
                    if ($date !== false) {
                        $creation_date = $date;
                        $extracted_metadata['Date Taken'] = date('Y-m-d H:i:s', $date);
                        break;
                    }
                }
            }
            
            // Extract other useful metadata
            if (isset($tags['title'])) $extracted_metadata['Title'] = $tags['title'];
            if (isset($tags['artist'])) $extracted_metadata['Artist'] = $tags['artist'];
            if (isset($tags['comment'])) $extracted_metadata['Comment'] = $tags['comment'];
            if (isset($tags['description'])) $extracted_metadata['Description'] = $tags['description'];
            if (isset($tags['encoder'])) $extracted_metadata['Encoder'] = $tags['encoder'];
            if (isset($tags['software'])) $extracted_metadata['Software'] = $tags['software'];
            
            // Android/smartphone specific metadata
            if (isset($tags['com.android.capture.fps'])) {
                $extracted_metadata['Capture FPS'] = $tags['com.android.capture.fps'] . ' fps';
            }
            
            // Device/brand information
            if (isset($tags['major_brand'])) $extracted_metadata['Format'] = strtoupper($tags['major_brand']);
            if (isset($tags['compatible_brands'])) $extracted_metadata['Compatible Formats'] = $tags['compatible_brands'];
            
            // Extract GPS coordinates if available
            $gps_coords = self::extractVideoGPSCoordinates($tags);
            if ($show_gps_coords && $gps_coords) {
                $extracted_metadata['GPS Latitude'] = number_format($gps_coords['latitude'], 6) . '° ' . $gps_coords['lat_ref'];
                $extracted_metadata['GPS Longitude'] = number_format($gps_coords['longitude'], 6) . '° ' . $gps_coords['lon_ref'];
                $extracted_metadata['Google Maps Link'] = '<a style="color: lightseagreen" href="https://maps.google.com/maps?q=' . $gps_coords['latitude'] . ',' . $gps_coords['longitude'] .'" target="_blank">View on Maps</a>';
            }
        }
        
        // Extract stream metadata (video/audio streams)
        if (isset($metadata['streams']) && is_array($metadata['streams'])) {
            foreach ($metadata['streams'] as $stream) {
                if ($stream['codec_type'] === 'video') {
                    // Video stream information
                    if (isset($stream['width'])) $extracted_metadata['Width'] = $stream['width'] . ' pixels';
                    if (isset($stream['height'])) $extracted_metadata['Height'] = $stream['height'] . ' pixels';
                    if (isset($stream['codec_name'])) $extracted_metadata['Video Codec'] = strtoupper($stream['codec_name']);
                    if (isset($stream['bit_rate'])) $extracted_metadata['Video Bitrate'] = round($stream['bit_rate'] / 1000) . ' kbps';
                    if (isset($stream['r_frame_rate'])) {
                        $fps_parts = explode('/', $stream['r_frame_rate']);
                        if (count($fps_parts) == 2 && $fps_parts[1] != 0) {
                            $fps = round($fps_parts[0] / $fps_parts[1], 2);
                            $extracted_metadata['Frame Rate'] = $fps . ' fps';
                        }
                    }
                    if (isset($stream['duration'])) {
                        $duration = (float)$stream['duration'];
                        $extracted_metadata['Duration'] = gmdate('H:i:s', $duration);
                    }
                    
                    // Check for GPS in video stream tags
                    if (isset($stream['tags']) && !$gps_coords) {
                        $stream_gps = self::extractVideoGPSCoordinates($stream['tags']);
                        if ($show_gps_coords && $stream_gps) {
                            $extracted_metadata['GPS Latitude'] = number_format($stream_gps['latitude'], 6) . '° ' . $stream_gps['lat_ref'];
                            $extracted_metadata['GPS Longitude'] = number_format($stream_gps['longitude'], 6) . '° ' . $stream_gps['lon_ref'];
                            $extracted_metadata['Google Maps Link'] = '<a style="color: lightseagreen" href="https://maps.google.com/maps?q=' . $stream_gps['latitude'] . ',' . $stream_gps['longitude'] .'" target="_blank">View on Maps</a>';
                        }
                    }
                } elseif ($stream['codec_type'] === 'audio') {
                    // Audio stream information
                    if (isset($stream['codec_name'])) $extracted_metadata['Audio Codec'] = strtoupper($stream['codec_name']);
                    if (isset($stream['bit_rate'])) $extracted_metadata['Audio Bitrate'] = round($stream['bit_rate'] / 1000) . ' kbps';
                    if (isset($stream['sample_rate'])) $extracted_metadata['Sample Rate'] = $stream['sample_rate'] . ' Hz';
                    if (isset($stream['channels'])) $extracted_metadata['Audio Channels'] = $stream['channels'];
                }
            }
        }
        
        // Add duration from format if not found in streams
        if (!isset($extracted_metadata['Duration']) && isset($metadata['format']['duration'])) {
            $duration = (float)$metadata['format']['duration'];
            $extracted_metadata['Duration'] = gmdate('H:i:s', $duration);
        }
        
        // Add file size
        if (isset($metadata['format']['size'])) {
            $size = (int)$metadata['format']['size'];
            if ($size < 1024 * 1024) {
                $extracted_metadata['File Size'] = round($size / 1024, 1) . ' KB';
            } else if ($size < 1024 * 1024 * 1024) {
                $extracted_metadata['File Size'] = round($size / (1024 * 1024), 1) . ' MB';
            } else {
                $extracted_metadata['File Size'] = round($size / (1024 * 1024 * 1024), 1) . ' GB';
            }
        }
        
        return array(
            'creation_date' => $creation_date,
            'metadata' => $extracted_metadata
        );
    }
    
    public static function getAudioMetadata($audio_path) {
        global $ffprobe_path;
        
        // Determine ffprobe executable path
        $ffprobe_cmd = !empty($ffprobe_path) ? $ffprobe_path : 'ffprobe';
        
        // Get both format and stream metadata for audio
        $command = "\"{$ffprobe_cmd}\" -v quiet -print_format json -show_format -show_streams \"{$audio_path}\"";
        error_log("Running ffprobe command for audio: " . $command);
        $output = shell_exec($command);
        
        if (empty($output)) {
            error_log("FFprobe returned empty output for audio: " . $audio_path);
            return null;
        }
        
        $metadata = json_decode($output, true);
        
        if (empty($metadata)) {
            error_log("Failed to decode JSON from ffprobe output for audio: " . $audio_path);
            return null;
        }
        
        error_log("Successfully decoded ffprobe metadata for audio: " . $audio_path);
        
        $extracted_metadata = array();
        $creation_date = null;
        
        // Extract format metadata (container level)
        if (isset($metadata['format']) && isset($metadata['format']['tags'])) {
            $tags = $metadata['format']['tags'];
            
            // Try different possible tag names for creation date
            $date_keys = ['creation_time', 'date', 'DATE', 'TDRC', 'year', 'YEAR'];
            
            foreach ($date_keys as $key) {
                if (isset($tags[$key])) {
                    $date = strtotime($tags[$key]);
                    if ($date !== false) {
                        $creation_date = $date;
                        $extracted_metadata['Date Created'] = date('Y-m-d H:i:s', $date);
                        break;
                    }
                }
            }
            
            // Extract audio-specific metadata
            if (isset($tags['title']) || isset($tags['TITLE'])) {
                $extracted_metadata['Title'] = $tags['title'] ?? $tags['TITLE'];
            }
            if (isset($tags['artist']) || isset($tags['ARTIST'])) {
                $extracted_metadata['Artist'] = $tags['artist'] ?? $tags['ARTIST'];
            }
            if (isset($tags['album']) || isset($tags['ALBUM'])) {
                $extracted_metadata['Album'] = $tags['album'] ?? $tags['ALBUM'];
            }
            if (isset($tags['genre']) || isset($tags['GENRE'])) {
                $extracted_metadata['Genre'] = $tags['genre'] ?? $tags['GENRE'];
            }
            if (isset($tags['track']) || isset($tags['TRACK'])) {
                $extracted_metadata['Track'] = $tags['track'] ?? $tags['TRACK'];
            }
            if (isset($tags['albumartist']) || isset($tags['ALBUMARTIST'])) {
                $extracted_metadata['Album Artist'] = $tags['albumartist'] ?? $tags['ALBUMARTIST'];
            }
            if (isset($tags['composer']) || isset($tags['COMPOSER'])) {
                $extracted_metadata['Composer'] = $tags['composer'] ?? $tags['COMPOSER'];
            }
            if (isset($tags['comment']) || isset($tags['COMMENT'])) {
                $extracted_metadata['Comment'] = $tags['comment'] ?? $tags['COMMENT'];
            }
        }
        
        // Extract stream metadata (audio streams)
        if (isset($metadata['streams']) && is_array($metadata['streams'])) {
            foreach ($metadata['streams'] as $stream) {
                if ($stream['codec_type'] === 'audio') {
                    // Audio stream information
                    if (isset($stream['codec_name'])) {
                        $extracted_metadata['Audio Codec'] = strtoupper($stream['codec_name']);
                    }
                    if (isset($stream['bit_rate'])) {
                        $extracted_metadata['Bitrate'] = round($stream['bit_rate'] / 1000) . ' kbps';
                    }
                    if (isset($stream['sample_rate'])) {
                        $extracted_metadata['Sample Rate'] = number_format($stream['sample_rate']) . ' Hz';
                    }
                    if (isset($stream['channels'])) {
                        $channels = $stream['channels'];
                        $channel_text = $channels == 1 ? 'Mono' : ($channels == 2 ? 'Stereo' : $channels . ' channels');
                        $extracted_metadata['Channels'] = $channel_text;
                    }
                    if (isset($stream['duration'])) {
                        $duration = (float)$stream['duration'];
                        $extracted_metadata['Duration'] = gmdate('H:i:s', $duration);
                    }
                    
                    // Bits per sample
                    if (isset($stream['bits_per_raw_sample'])) {
                        $extracted_metadata['Bit Depth'] = $stream['bits_per_raw_sample'] . ' bit';
                    }
                    break; // Take first audio stream
                }
            }
        }
        
        // Add duration from format if not found in streams
        if (!isset($extracted_metadata['Duration']) && isset($metadata['format']['duration'])) {
            $duration = (float)$metadata['format']['duration'];
            $extracted_metadata['Duration'] = gmdate('H:i:s', $duration);
        }
        
        // Add file size
        if (isset($metadata['format']['size'])) {
            $size = (int)$metadata['format']['size'];
            if ($size < 1024 * 1024) {
                $extracted_metadata['File Size'] = round($size / 1024, 1) . ' KB';
            } else if ($size < 1024 * 1024 * 1024) {
                $extracted_metadata['File Size'] = round($size / (1024 * 1024), 1) . ' MB';
            } else {
                $extracted_metadata['File Size'] = round($size / (1024 * 1024 * 1024), 1) . ' GB';
            }
        }
        
        return array(
            'creation_date' => $creation_date,
            'metadata' => $extracted_metadata
        );
    }
    
    private static function extractVideoGPSCoordinates($tags) {
        // Check for various GPS coordinate formats in video metadata
        $gps_patterns = [
            // Android/smartphone location format: +47.4818-122.2018/
            'location' => '/^([+-]?\d+\.?\d*)([+-]\d+\.?\d*)(?:[+-]\d+\.?\d*)?\/?\s*$/',
            'location-eng' => '/^([+-]?\d+\.?\d*)([+-]\d+\.?\d*)(?:[+-]\d+\.?\d*)?\/?\s*$/',
            // Standard GPS patterns with comma/space separation
            'gps' => '/^([+-]?\d+\.?\d*)[,\s]+([+-]?\d+\.?\d*)/',
            // QuickTime location format
            'com.apple.quicktime.location.ISO6709' => '/^([+-]\d+\.?\d*)([+-]\d+\.?\d*)/',
            // Other possible formats
            'geo' => '/^([+-]?\d+\.?\d*)[,\s]+([+-]?\d+\.?\d*)/',
            'coordinates' => '/^([+-]?\d+\.?\d*)[,\s]+([+-]?\d+\.?\d*)/'
        ];
        
        foreach ($gps_patterns as $key => $pattern) {
            if (isset($tags[$key])) {
                $location_string = $tags[$key];
                
                if (preg_match($pattern, $location_string, $matches)) {
                    $latitude = (float)$matches[1];
                    $longitude = (float)$matches[2];
                    
                    // Validate coordinates are within valid ranges
                    if ($latitude >= -90 && $latitude <= 90 && $longitude >= -180 && $longitude <= 180) {
                        return array(
                            'latitude' => $latitude,
                            'longitude' => $longitude,
                            'lat_ref' => $latitude >= 0 ? 'N' : 'S',
                            'lon_ref' => $longitude >= 0 ? 'E' : 'W'
                        );
                    }
                }
            }
        }
        
        // Try to extract from individual lat/lon fields
        $lat_keys = ['latitude', 'lat', 'gps_latitude'];
        $lon_keys = ['longitude', 'lon', 'lng', 'gps_longitude'];
        
        $latitude = null;
        $longitude = null;
        
        foreach ($lat_keys as $lat_key) {
            if (isset($tags[$lat_key])) {
                $latitude = (float)$tags[$lat_key];
                break;
            }
        }
        
        foreach ($lon_keys as $lon_key) {
            if (isset($tags[$lon_key])) {
                $longitude = (float)$tags[$lon_key];
                break;
            }
        }
        
        if ($latitude !== null && $longitude !== null && 
            $latitude >= -90 && $latitude <= 90 && 
            $longitude >= -180 && $longitude <= 180) {
            return array(
                'latitude' => $latitude,
                'longitude' => $longitude,
                'lat_ref' => $latitude >= 0 ? 'N' : 'S',
                'lon_ref' => $longitude >= 0 ? 'E' : 'W'
            );
        }
        
        return null;
    }
    
    // Test if FFmpeg and FFprobe are accessible
    public static function testFFmpegTools() {
        global $ffmpeg_path, $ffprobe_path;
        
        $ffmpeg_cmd = !empty($ffmpeg_path) ? $ffmpeg_path : 'ffmpeg';
        $ffprobe_cmd = !empty($ffprobe_path) ? $ffprobe_path : 'ffprobe';
        
        // Test ffmpeg
        $ffmpeg_test = shell_exec("\"{$ffmpeg_cmd}\" -version 2>&1");
        $ffmpeg_works = strpos($ffmpeg_test, 'ffmpeg version') !== false;
        
        // Test ffprobe
        $ffprobe_test = shell_exec("\"{$ffprobe_cmd}\" -version 2>&1");
        $ffprobe_works = strpos($ffprobe_test, 'ffprobe version') !== false;
        
        error_log("FFmpeg test (cmd: {$ffmpeg_cmd}): " . ($ffmpeg_works ? 'WORKING' : 'FAILED'));
        error_log("FFprobe test (cmd: {$ffprobe_cmd}): " . ($ffprobe_works ? 'WORKING' : 'FAILED'));
        
        if (!$ffmpeg_works) {
            error_log("FFmpeg output: " . $ffmpeg_test);
        }
        if (!$ffprobe_works) {
            error_log("FFprobe output: " . $ffprobe_test);
        }
        
        return ['ffmpeg' => $ffmpeg_works, 'ffprobe' => $ffprobe_works];
    }
    
    // Size ranges for grouping files
    public static function getSizeRanges() {
        return [
            'Less than 5MB' => function($size) { return $size < 5 * 1024 * 1024; },
            '5MB to 25MB' => function($size) { return $size >= 5 * 1024 * 1024 && $size < 25 * 1024 * 1024; },
            '25MB to 50MB' => function($size) { return $size >= 25 * 1024 * 1024 && $size < 50 * 1024 * 1024; },
            '50MB to 250MB' => function($size) { return $size >= 50 * 1024 * 1024 && $size < 250 * 1024 * 1024; },
            '250MB to 500MB' => function($size) { return $size >= 250 * 1024 * 1024 && $size < 500 * 1024 * 1024; },
            '500MB to 1TB' => function($size) { return $size >= 500 * 1024 * 1024 && $size < 1024 * 1024 * 1024 * 1024; },
        ];
    }
    
    // File type filters
    public static function getFileTypeFilters() {
        return [
            'type_image' => function($file) {
                return strpos($file['path'], '.jpg') !== false || 
                       strpos($file['path'], '.jpeg') !== false || 
                       strpos($file['path'], '.png') !== false || 
                       strpos($file['path'], '.gif') !== false ||
                       strpos($file['path'], '.webp') !== false ||
                       strpos($file['path'], '.bmp') !== false ||
                       strpos($file['path'], '.ico') !== false ||
                       strpos($file['path'], '.apng') !== false ||
                       strpos($file['path'], '.svg') !== false;
            },
            'type_pdf' => function($file) {
                return strpos($file['path'], '.pdf') !== false;
            },
            'type_video' => function($file) {
                return strpos($file['path'], '.mp4') !== false || 
                       strpos($file['path'], '.webm') !== false ||
                       strpos($file['path'], '.ogg') !== false ||
                       strpos($file['path'], '.ogv') !== false ||
                       strpos($file['path'], '.3gp') !== false ||
                       strpos($file['path'], '.qt') !== false ||
                       strpos($file['path'], '.mov') !== false ||
                       strpos($file['path'], '.qtff') !== false ||
                       strpos($file['path'], '.avi') !== false;
            },
            'type_gif' => function($file) {
                return strpos($file['path'], '.gif') !== false;
            },
            'type_jpg' => function($file) {
                return strpos($file['path'], '.jpg') !== false || 
                       strpos($file['path'], '.jpeg') !== false;
            },
            'type_png' => function($file) {
                return strpos($file['path'], '.png') !== false;
            },
            'type_webp' => function($file) {
                return strpos($file['path'], '.webp') !== false;
            },
            'type_bmp' => function($file) {
                return strpos($file['path'], '.bmp') !== false;
            },
            'type_svg' => function($file) {
                return strpos($file['path'], '.svg') !== false;
            },
            'type_mp4' => function($file) {
                return strpos($file['path'], '.mp4') !== false;
            },
            'type_webm' => function($file) {
                return strpos($file['path'], '.webm') !== false;
            },
            'type_ogg' => function($file) {
                return strpos($file['path'], '.ogg') !== false ||
                       strpos($file['path'], '.ogv') !== false;
            },
            'type_3gp' => function($file) {
                return strpos($file['path'], '.3gp') !== false;
            },
            'type_mov' => function($file) {
                return strpos($file['path'], '.qt') !== false ||
                       strpos($file['path'], '.mov') !== false ||
                       strpos($file['path'], '.qtff') !== false;
            },
            'type_avi' => function($file) {
                return strpos($file['path'], '.avi') !== false;
            },
            'type_audio' => function($file) {
                return strpos($file['path'], '.mp3') !== false || 
                       strpos($file['path'], '.opus') !== false ||
                       strpos($file['path'], '.wav') !== false ||
                       strpos($file['path'], '.flac') !== false ||
                       strpos($file['path'], '.aac') !== false;
            },
            'type_mp3' => function($file) {
                return strpos($file['path'], '.mp3') !== false;
            },
            'type_opus' => function($file) {
                return strpos($file['path'], '.opus') !== false;
            },
            'type_wav' => function($file) {
                return strpos($file['path'], '.wav') !== false;
            },
            'type_flac' => function($file) {
                return strpos($file['path'], '.flac') !== false;
            },
            'type_aac' => function($file) {
                return strpos($file['path'], '.aac') !== false;
            }
        ];
    }
}

// Utility functions for file grouping and sorting
class GalleryUtils {
    
    public static function formatFileSize($bytes) {
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' KB';
        } else if ($bytes < 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024), 1) . ' MB';
        } else {
            return round($bytes / (1024 * 1024 * 1024), 1) . ' GB';
        }
    }
    
    public static function groupFilesBySize($files, $sort_direction = 'desc') {
        $grouped_files = [];
        $size_ranges = GalleryConfig::getSizeRanges();
        
        foreach ($files as $file) {
            foreach ($size_ranges as $range_name => $condition) {
                if ($condition($file['size'])) {
                    if (!isset($grouped_files[$range_name])) {
                        $grouped_files[$range_name] = [];
                    }
                    $grouped_files[$range_name][] = $file;
                    break;
                }
            }
        }
        
        // Sort groups based on direction
        if ($sort_direction == 'asc') {
            krsort($grouped_files); // For smallest first, reverse the group order
        } else {
            ksort($grouped_files); // For largest first, keep natural order
        }
        
        return $grouped_files;
    }
    
    public static function groupFilesByName($files, $sort_direction = 'asc') {
        $grouped_files = [];
        
        foreach ($files as $file) {
            $first_letter = strtoupper(substr($file['name'], 0, 1));
            if (!ctype_alpha($first_letter)) {
                $first_letter = '#'; // Group non-alphabetic starts together
            }
            
            if (!isset($grouped_files[$first_letter])) {
                $grouped_files[$first_letter] = [];
            }
            
            $grouped_files[$first_letter][] = $file;
        }
        
        // Sort groups alphabetically
        if ($sort_direction == 'asc') {
            ksort($grouped_files);
        } else {
            krsort($grouped_files);
        }
        
        return $grouped_files;
    }
    
    public static function groupFilesByDate($files, $date_field, $sort_direction = 'desc') {
        $grouped_files = [];
        
        foreach ($files as $file) {
            $date = $file[$date_field];
            $day = date('Y-m-d', $date);
            
            if (!isset($grouped_files[$day])) {
                $grouped_files[$day] = [];
            }
            
            $grouped_files[$day][] = $file;
        }
        
        // Sort groups by date
        if ($sort_direction == 'asc') {
            ksort($grouped_files);
        } else {
            krsort($grouped_files);
        }
        
        return $grouped_files;
    }
    
    public static function filterFilesByType($files, $filter_type) {
        if (!$filter_type || $filter_type === 'type_all') {
            return $files;
        }
        
        $filters = GalleryConfig::getFileTypeFilters();
        
        if (isset($filters[$filter_type])) {
            return array_filter($files, $filters[$filter_type]);
        }
        
        return $files;
    }
}

// Get the current directory from URL or use default
$current_dir = isset($_GET['dir']) ? $_GET['dir'] : $base_dir;
// Validate directory for security
if (!is_dir($current_dir) || strpos(realpath($current_dir), realpath('./')) !== 0) {
    $current_dir = $base_dir;
}

// Create content and thumbnail directories if they don't exist
if (!file_exists($base_dir)) {
    mkdir($base_dir, 0755, true);
}

if (!file_exists($thumbs_dir)) {
    mkdir($thumbs_dir, 0755, true);
}

// Function to create thumbnails
function create_thumbnail($source, $destination, $width) {
    // Get image dimensions
    $dims = getimagesize($source);
    $w = $dims[0];
    $h = $dims[1];
    
    // Calculate new dimensions with preserved aspect ratio
    $ratio = $w / $h;
    if ($ratio < 1) { // Portrait
        $new_width = $width * $ratio;
        $new_height = $width;
    } else {         // Landscape
        $new_width = $width;
        $new_height = $width / $ratio;
    }
    
    // Create blank canvas
    $canvas = imagecreatetruecolor($new_width, $new_height);
    
    // Load original image
    if (exif_imagetype($source) == IMAGETYPE_JPEG) {
        $img = imagecreatefromjpeg($source);
    } elseif (exif_imagetype($source) == IMAGETYPE_PNG) {
        $img = imagecreatefrompng($source);
    } elseif (exif_imagetype($source) == IMAGETYPE_GIF) {
        $img = imagecreatefromgif($source);
    }
    
    // Copy original image to canvas
    imagecopyresampled($canvas, $img, 0, 0, 0, 0,
                      $new_width, $new_height, $w, $h);
    
    // Save thumbnail
    imagejpeg($canvas, $destination, 85); // Quality: 85%
    imagedestroy($canvas);
    imagedestroy($img);
}

function gps2Num($coordPart) {
    $parts = explode('/', $coordPart);
    return count($parts) > 1 ? floatval($parts[0]) / floatval($parts[1]) : floatval($parts[0]);
}

// Function to extract GPS coordinates from EXIF data
function get_gps_coordinates($exif) {
    // Check if GPS data exists - it can be in either $exif['GPS'] or directly in $exif
    $gps_lat = null;
    $gps_lon = null;
    $gps_lat_ref = null;
    $gps_lon_ref = null;
    
    // First check if GPS data is in a GPS sub-array
    if (isset($exif['GPS']) && 
        isset($exif['GPS']['GPSLatitude']) && 
        isset($exif['GPS']['GPSLongitude']) &&
        isset($exif['GPS']['GPSLatitudeRef']) && 
        isset($exif['GPS']['GPSLongitudeRef'])) {
        
        $gps_lat_ref = $exif['GPS']['GPSLatitudeRef'];
        $gps_lat = $exif['GPS']['GPSLatitude'];
        $gps_lon_ref = $exif['GPS']['GPSLongitudeRef'];
        $gps_lon = $exif['GPS']['GPSLongitude'];
    }
    // Otherwise check if GPS data is directly in the main EXIF array
    else if (isset($exif['GPSLatitude']) && 
             isset($exif['GPSLongitude']) &&
             isset($exif['GPSLatitudeRef']) && 
             isset($exif['GPSLongitudeRef'])) {
        
        $gps_lat_ref = $exif['GPSLatitudeRef'];
        $gps_lat = $exif['GPSLatitude'];
        $gps_lon_ref = $exif['GPSLongitudeRef'];
        $gps_lon = $exif['GPSLongitude'];
    }
    
    // If no GPS data found, return null
    if (!$gps_lat || !$gps_lon || !$gps_lat_ref || !$gps_lon_ref) {
        return null;
    }
    
    $GPSLatitudeRef = $gps_lat_ref;
    $GPSLatitude = $gps_lat;
    $GPSLongitudeRef = $gps_lon_ref;
    $GPSLongitude = $gps_lon;
    
    // Convert GPS coordinates to decimal degrees
    $lat_degrees = count($GPSLatitude) > 0 ? gps2Num($GPSLatitude[0]) : 0;
    $lat_minutes = count($GPSLatitude) > 1 ? gps2Num($GPSLatitude[1]) : 0;
    $lat_seconds = count($GPSLatitude) > 2 ? gps2Num($GPSLatitude[2]) : 0;
    
    $lon_degrees = count($GPSLongitude) > 0 ? gps2Num($GPSLongitude[0]) : 0;
    $lon_minutes = count($GPSLongitude) > 1 ? gps2Num($GPSLongitude[1]) : 0;
    $lon_seconds = count($GPSLongitude) > 2 ? gps2Num($GPSLongitude[2]) : 0;
    
    // Calculate decimal degrees
    $latitude = $lat_degrees + ($lat_minutes / 60) + ($lat_seconds / 3600);
    $longitude = $lon_degrees + ($lon_minutes / 60) + ($lon_seconds / 3600);
    
    // Apply direction (N/S for latitude, E/W for longitude)
    if ($GPSLatitudeRef == 'S') {
        $latitude = -$latitude;
    }
    if ($GPSLongitudeRef == 'W') {
        $longitude = -$longitude;
    }
    
    return array(
        'latitude' => $latitude,
        'longitude' => $longitude,
        'lat_ref' => $GPSLatitudeRef,
        'lon_ref' => $GPSLongitudeRef
    );
}

// Fix issue in get_media_files function
function get_media_files($dir) {
    global $show_gps_coords; // Make global variable accessible inside function
    $files = array();
    
    // Check if directory exists and is not empty
    if (!file_exists($dir) || !is_dir($dir) || count(scandir($dir)) <= 2) {
        return $files; // Return empty array if directory doesn't exist or is empty
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $file_info) {
        // Skip directories - we don't need isDot() now as we're using SKIP_DOTS flag
        if ($file_info->isDir()) continue;
        
        $path = $file_info->getPathname();
        $filename = $file_info->getFilename();
        
        // Check for supported media types
        if (preg_match(GalleryConfig::SUPPORTED_EXTENSIONS, $filename)) {
            $type = null;
            $taken = 0;
            
            // Handle different file types
            if (preg_match(GalleryConfig::IMAGE_EXTENSIONS, $filename)) {
                try {
                    // Use centralized image MIME type detection
                    $type = GalleryConfig::getImageMimeType($filename);
                    
                    // For PDFs, set special handling
                    if (GalleryConfig::isPDF($filename)) {
                        $pdf_info = GalleryConfig::getPDFInfo($path);
                        $exif = array(
                            'File Type' => 'PDF Document',
                            'Format' => 'Portable Document Format',
                            'Pages' => $pdf_info['pages'] . ' page' . ($pdf_info['pages'] != 1 ? 's' : ''),
                            'Title' => $pdf_info['title']
                        );
                    } else if (preg_match('/\.svg$/i', $filename)) {
                        // SVG files - basic info only
                        $exif = array(
                            'File Type' => 'SVG Vector Image',
                            'Format' => 'Scalable Vector Graphics'
                        );
                    } else {
                        // Try to get EXIF data for traditional image formats
                        $img_type = @exif_imagetype($path);
                        if ($img_type && ($img_type == IMAGETYPE_JPEG || $img_type == IMAGETYPE_GIF || $img_type == IMAGETYPE_PNG)) {
                            $raw_exif = @exif_read_data($path);
                            if ($raw_exif) {
                                // Extract useful EXIF information
                                $exif = array();
                                
                                // Basic camera info
                                if (isset($raw_exif['Make'])) $exif['Camera Make'] = $raw_exif['Make'];
                                if (isset($raw_exif['Model'])) $exif['Camera Model'] = $raw_exif['Model'];
                                if (isset($raw_exif['DateTimeOriginal'])) {
                                    $exif['Date Taken'] = $raw_exif['DateTimeOriginal'];
                                    $taken = strtotime($raw_exif['DateTimeOriginal']);
                                }
                                
                                // Camera settings
                                if (isset($raw_exif['COMPUTED']['ApertureFNumber'])) $exif['Aperture'] = $raw_exif['COMPUTED']['ApertureFNumber'];
                                if (isset($raw_exif['ExposureTime'])) $exif['Exposure Time'] = $raw_exif['ExposureTime'];
                                if (isset($raw_exif['ISOSpeedRatings'])) $exif['ISO'] = $raw_exif['ISOSpeedRatings'];
                                if (isset($raw_exif['FocalLength'])) $exif['Focal Length'] = $raw_exif['FocalLength'];
                                if (isset($raw_exif['Flash'])) $exif['Flash'] = $raw_exif['Flash'];
                                
                                // Image dimensions
                                if (isset($raw_exif['COMPUTED']['Width'])) $exif['Width'] = $raw_exif['COMPUTED']['Width'] . ' pixels';
                                if (isset($raw_exif['COMPUTED']['Height'])) $exif['Height'] = $raw_exif['COMPUTED']['Height'] . ' pixels';
                                
                                // GPS data if available
                                $gps_coords = get_gps_coordinates($raw_exif);
                                if ($show_gps_coords && $gps_coords) {
                                    $exif['GPS Latitude'] = number_format($gps_coords['latitude'], 6) . '° ' . $gps_coords['lat_ref'];
                                    $exif['GPS Longitude'] = number_format($gps_coords['longitude'], 6) . '° ' . $gps_coords['lon_ref'];
                                    $exif['Google Maps Link'] = '<a href="https://maps.google.com/maps?q=' . $gps_coords['latitude'] . ',' . $gps_coords['longitude'] .'" target="_blank">View on Maps</a>';
                                }
                                
                                // Software/processing
                                if (isset($raw_exif['Software'])) $exif['Software'] = $raw_exif['Software'];
                            } else {
                                $exif = null;
                            }
                        } else {
                            // For other image formats, try to get basic info
                            $exif = array();
                            $dims = @getimagesize($path);
                            if ($dims) {
                                $exif['Width'] = $dims[0] . ' pixels';
                                $exif['Height'] = $dims[1] . ' pixels';
                                $exif['File Type'] = strtoupper(pathinfo($filename, PATHINFO_EXTENSION)) . ' Image';
                            }
                        }
                    }
                } catch (Exception $e) {
                    // Silently handle EXIF errors
                    $exif = null;
                }
            } else if (GalleryConfig::isVideo($filename)) {
                // Use centralized video MIME type detection
                $type = GalleryConfig::getVideoMimeType($filename);
                
                // Extract comprehensive metadata from video using centralized method
                $video_metadata = GalleryConfig::getVideoMetadata($path);
                if ($video_metadata) {
                    $taken = $video_metadata['creation_date'] ?: 0;
                    $exif = $video_metadata['metadata'];
                    
                    // Debug: Log video metadata extraction
                    error_log("Video metadata for {$filename}: " . print_r($video_metadata, true));
                } else {
                    $taken = 0;
                    $exif = null;
                    error_log("No video metadata found for {$filename}");
                }
            } else if (GalleryConfig::isAudio($filename)) {
                // Use centralized audio MIME type detection
                $type = GalleryConfig::getAudioMimeType($filename);
                
                // Extract comprehensive metadata from audio using centralized method
                $audio_metadata = GalleryConfig::getAudioMetadata($path);
                if ($audio_metadata) {
                    $taken = $audio_metadata['creation_date'] ?: 0;
                    $exif = $audio_metadata['metadata'];
                    
                    // Debug: Log audio metadata extraction
                    error_log("Audio metadata for {$filename}: " . print_r($audio_metadata, true));
                } else {
                    $taken = 0;
                    $exif = null;
                    error_log("No audio metadata found for {$filename}");
                }
            }
            
            // If no taken date found, use modification time
            if ($taken == 0) {
                $taken = $file_info->getMTime();
            }
            
            $files[] = array(
                'path' => $path,
                'type' => $type,
                'modified' => $file_info->getMTime(),
                'taken' => $taken,
                'size' => $file_info->getSize(),
                'name' => $filename,
                'exif' => isset($exif) ? $exif : null
            );
        }
    }
    
    return $files;
}

// Main logic
$media_files = get_media_files($current_dir);

// Test FFmpeg tools availability
$ffmpeg_status = GalleryConfig::testFFmpegTools();
if (!$ffmpeg_status['ffmpeg'] || !$ffmpeg_status['ffprobe']) {
    error_log("WARNING: FFmpeg tools not working properly. Video thumbnails and metadata will not work.");
}

// Get sort and filter parameters from URL
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'modified_desc';
$filter = isset($_GET['filter']) ? $_GET['filter'] : null;

// Separate filtering and sorting logic
$filter_type = null;
$actual_sort = $sort;

// Check if this is a filter request from the old sort parameter (for backwards compatibility)
if (strpos($sort, 'type_') === 0) {
    $filter_type = $sort;
    // For filtered results, default to modified_desc sorting unless specified
    $actual_sort = 'modified_desc';
} else if ($filter && strpos($filter, 'type_') === 0) {
    // Use the new separate filter parameter
    $filter_type = $filter;
    $actual_sort = $sort; // Keep the specified sort
} else {
    $filter_type = 'type_all'; // Show all files when sorting
}

// Get pagination parameters
$items_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 500;
$items_per_page = max(25, min(500, $items_per_page)); // Enforce limits
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

// Sort the media files based on the selected criteria
usort($media_files, function($a, $b) use ($actual_sort) {
    switch($actual_sort) {
        case 'name_asc':
            return strcasecmp($a['name'], $b['name']);
        case 'name_desc':
            return strcasecmp($b['name'], $a['name']);
        case 'modified_asc':
            return $a['modified'] - $b['modified'];
        case 'modified_desc':
            return $b['modified'] - $a['modified'];
        case 'taken_asc':
            return $a['taken'] - $b['taken'];
        case 'taken_desc':
            return $b['taken'] - $a['taken'];
        case 'size_asc':
            return $a['size'] - $b['size'];
        case 'size_desc':
            return $b['size'] - $a['size'];
        default:
            return $b['modified'] - $a['modified'];
    }
});

// Filter the media files by type if a type filter is selected
$media_files = GalleryUtils::filterFilesByType($media_files, $filter_type);

// After sorting media files, group them by date, size or name
$grouped_files = [];

// Special handling for type filters
if ($filter_type && $filter_type !== 'type_all') {
    // For type filters, we'll group by date based on the actual sort
    $date_field = (strpos($actual_sort, 'taken') === 0) ? 'taken' : 'modified';
    $sort_direction = (strpos($actual_sort, '_asc') !== false) ? 'asc' : 'desc';
    $grouped_files = GalleryUtils::groupFilesByDate($media_files, $date_field, $sort_direction);
} else if ($actual_sort == 'size_desc' || $actual_sort == 'size_asc') {
    // Group files by size range
    $sort_direction = ($actual_sort == 'size_asc') ? 'asc' : 'desc';
    $grouped_files = GalleryUtils::groupFilesBySize($media_files, $sort_direction);
} else if ($actual_sort == 'name_asc' || $actual_sort == 'name_desc') {
    // Group files by first letter of filename
    $sort_direction = ($actual_sort == 'name_asc') ? 'asc' : 'desc';
    $grouped_files = GalleryUtils::groupFilesByName($media_files, $sort_direction);
} else {
    // Group files by date (either modified or taken date depending on sort)
    $date_field = (strpos($actual_sort, 'taken') === 0) ? 'taken' : 'modified';
    $sort_direction = (strpos($actual_sort, '_asc') !== false) ? 'asc' : 'desc';
    $grouped_files = GalleryUtils::groupFilesByDate($media_files, $date_field, $sort_direction);
}

// Filter out empty groups
$grouped_files = array_filter($grouped_files, function($group) {
    return count($group) > 0;
});

// Calculate pagination for all files
$all_files = [];
foreach ($grouped_files as $group) {
    $all_files = array_merge($all_files, $group);
}
$total_files = count($all_files);
$total_pages = ceil($total_files / $items_per_page);
$current_page = min($current_page, max(1, $total_pages)); // Ensure valid page

// For name sorting, use standard pagination but rebuild groups afterwards
if ($actual_sort == 'name_asc' || $actual_sort == 'name_desc') {
    // Use standard file-based pagination first
    $start_index = ($current_page - 1) * $items_per_page;
    $paginated_files = array_slice($all_files, $start_index, $items_per_page);
    
    // Rebuild grouped files with paginated data, maintaining letter groups
    $sort_direction = ($actual_sort == 'name_asc') ? 'asc' : 'desc';
    $grouped_files = GalleryUtils::groupFilesByName($paginated_files, $sort_direction);
} else {
    // For other sorting types, use standard file-based pagination
    $start_index = ($current_page - 1) * $items_per_page;
    $paginated_files = array_slice($all_files, $start_index, $items_per_page);

    // Rebuild grouped files with paginated data
    $grouped_files = [];
    if ($actual_sort == 'size_desc' || $actual_sort == 'size_asc') {
        $sort_direction = ($actual_sort == 'size_asc') ? 'asc' : 'desc';
        $grouped_files = GalleryUtils::groupFilesBySize($paginated_files, $sort_direction);
    } else {
        // Group paginated files by date
        $date_field = (strpos($actual_sort, 'taken') === 0) ? 'taken' : 'modified';
        if (strpos($filter_type, 'type_') === 0) {
            $date_field = 'modified';
        }
        
        $sort_direction = (strpos($actual_sort, '_asc') !== false) ? 'asc' : 'desc';
        $grouped_files = GalleryUtils::groupFilesByDate($paginated_files, $date_field, $sort_direction);
    }
}

// Generate thumbnails for each media file (skip PDFs - handled by JavaScript)
foreach ($paginated_files as $file) {
    // Use centralized detection and thumbnail path generation
    $is_video = GalleryConfig::isVideo($file['path']);
    $is_image = GalleryConfig::isImage($file['path']);
    $is_audio = GalleryConfig::isAudio($file['path']);
    $is_pdf = GalleryConfig::isPDF($file['path']);
    
    // Skip PDFs - they'll be handled entirely by JavaScript
    if ($is_pdf) {
        continue;
    }
    
    if ($is_video) {
        $thumb_path = GalleryConfig::getVideoThumbnailPath($file['path'], $thumbs_dir);
    } else if ($is_audio) {
        $thumb_path = GalleryConfig::getAudioThumbnailPath($file['path'], $thumbs_dir);
    } else if ($is_image) {
        $thumb_path = GalleryConfig::getImageThumbnailPath($file['path'], $thumbs_dir);
    } else {
        // Skip unsupported file types
        continue;
    }
    
    error_log("Processing file: {$file['name']}, is_video: " . ($is_video ? 'true' : 'false') . ", is_image: " . ($is_image ? 'true' : 'false') . ", thumb_path: {$thumb_path}");
    
    // Skip if thumbnail already exists
    if (file_exists($thumb_path)) {
        error_log("Thumbnail already exists: {$thumb_path}");
        continue;
    }
    
    error_log("Creating thumbnail for: {$file['path']} -> {$thumb_path}");
    
    // Handle different file types
    if ($is_video) {
        GalleryConfig::createVideoThumbnail($file['path'], $thumb_path);
    } else if ($is_audio) {
        GalleryConfig::createAudioThumbnail($file['path'], $thumb_path, $thumb_width);
    } else if ($is_image) {
        // Use centralized image thumbnail creation
        $success = GalleryConfig::createImageThumbnail($file['path'], $thumb_path, $thumb_width);
        if (!$success) {
            // Fallback to old method for basic image types
            $img_type = @exif_imagetype($file['path']);
            if ($img_type) {
                switch ($img_type) {
                    case IMAGETYPE_JPEG:
                    case IMAGETYPE_PNG:
                    case IMAGETYPE_GIF:
                        create_thumbnail($file['path'], $thumb_path, $thumb_width);
                        break;
                }
            }
        }
    }
    
    // Check if thumbnail was successfully created
    if (file_exists($thumb_path)) {
        error_log("Thumbnail successfully created: {$thumb_path}");
    } else {
        error_log("FAILED to create thumbnail: {$thumb_path}");
    }
}

// Get current directory name for display
$current_dir_name = basename($current_dir);
if ($current_dir_name == '.' || $current_dir_name == '') {
    $current_dir_name = 'Media Gallery';
}

// Simple HTML viewer
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($current_dir_name) ?> - Media Gallery</title>
    
    <!-- PDF.js Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    
    <style>
        :root {
            --primary-color: #3498db;
            --primary-dark: #2980b9;
            --sidebar-bg: #2c3e50;
            --sidebar-item: #34495e;
            --border-radius: 4px;
            --transition: 0.2s;
            --shadow: 0 2px 10px rgba(0,0,0,0.1);
            --text-muted: #7f8c8d;
            --white-alpha: rgba(255,255,255,0.1);
        }

        * { box-sizing: border-box; }

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background-color: #f5f5f5;
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Layout */
        .sidebar {
            width: 250px;
            min-width: 250px;
            max-width: 250px;
            background: var(--sidebar-bg);
            padding: 20px;
            overflow-y: auto;
            position: sticky;
            top: 0;
            height: 100vh;
            color: white;
            box-shadow: var(--shadow);
        }

        .sidebar-header { margin-bottom: 20px; text-align: center; }
        .controls { margin-bottom: 1.5rem; }
        .controls .group { margin-bottom: 10px; }
        .controls .group-label {
            font-size: 0.9em;
            opacity: 0.8;
            margin-bottom: 5px;
            display: block;
        }

        /* Interactive Elements */
        .controls select,
        .items-per-page select {
            width: 100%;
            padding: 8px 12px;
            border-radius: var(--border-radius);
            border: 1px solid #ddd;
            background: var(--sidebar-item);
            color: white;
        }
        .filesize {
            color: blueviolet;
        }
        .group-controls {
            display: flex;
            margin-bottom: 10px;
            gap: 10px;
        }

        .group-controls button {
            flex: 1;
            background: var(--sidebar-item);
            color: white;
            border: none;
            padding: 8px 0;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 0.9em;
            transition: background var(--transition);
        }

        .group-controls button:hover { background: var(--primary-color); }

        /* Navigation */
        .group-nav {
            margin-top: 20px;
            border-top: 1px solid var(--white-alpha);
            padding-top: 15px;
        }

        .nav-section { margin-bottom: 20px; }
        .nav-section-title {
            font-weight: bold;
            margin-bottom: 10px;
            text-transform: uppercase;
            font-size: 0.8em;
            letter-spacing: 1px;
            color: rgba(255, 255, 255, 0.6);
        }

        .folder-nav { margin-bottom: 20px; }

        .folder-nav-item,
        .group-nav-item {
            padding: 10px 15px;
            border-radius: var(--border-radius);
            margin-bottom: 5px;
            cursor: pointer;
            transition: background var(--transition);
            display: flex;
            align-items: center;
        }

        .group-nav-item { justify-content: space-between; }

        .folder-nav-item:hover,
        .group-nav-item:hover { background: var(--sidebar-item); }

        .folder-nav-item.active,
        .group-nav-item.active { background: var(--primary-color); }

        .folder-icon { margin-right: 10px; font-size: 1.2em; }

        .group-nav-item .badge {
            background: rgba(255,255,255,0.2);
            border-radius: 10px;
            padding: 2px 8px;
            font-size: 0.8em;
            min-width: 24px;
            text-align: center;
        }

        .group-name {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 180px;
        }

        /* Main Content */
        .content { flex-grow: 1; padding: 20px; overflow-y: auto; }
        .date-section { margin-bottom: 40px; }
        .date-header {
            margin-bottom: 15px;
            padding: 10px;
            background: var(--primary-color);
            color: white;
            border-radius: var(--border-radius);
            font-weight: bold;
        }

        /* Gallery Grid */
        .date-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 400px));
            grid-gap: 15px;
            width: 100%;
            justify-content: start;
        }

        .item {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: var(--shadow);
            background: white;
            cursor: pointer;
            transition: transform var(--transition);
            aspect-ratio: 1;
            min-width: 250px;
            min-height: 250px;
            max-width: 400px;
            max-height: 400px;
        }

        .item:hover { transform: translateY(-5px); }

        .item img, 
        .item video {
            width: 100%;
            height: 100%;
            display: block;
            border-radius: 8px;
            object-fit: cover;
        }

        .info {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0,0,0,0.6);
            color: white;
            padding: 10px;
            font-size: 0.9em;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .info-filename {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
        }

        .item:hover .info { opacity: 1; }

        .play-button {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 50px;
            opacity: 0.8;
            pointer-events: none;
        }

        /* Audio play button - only visible on hover */
        .audio-play-button-hover {
            position: absolute;
            top: 35%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(46, 204, 113, 0.9);
            color: white;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            opacity: 0;
            transition: all 0.3s ease;
            pointer-events: auto;
            backdrop-filter: blur(4px);
            border: 2px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            cursor: pointer;
            z-index: 10;
        }

        .item:hover .audio-play-button-hover {
            opacity: 1;
            transform: translate(-50%, -50%) scale(1.05);
        }

        .item[data-audio-playing="true"] .audio-play-button-hover {
            background: rgba(231, 76, 60, 0.9);
            opacity: 1;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: translate(-50%, -50%) scale(1); }
            50% { transform: translate(-50%, -50%) scale(1.05); }
            100% { transform: translate(-50%, -50%) scale(1); }
        }

        .pdf-thumbnail {
            width: 100%;
            height: 100%;
            object-fit: cover;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
        }

        .pdf-icon {
            position: absolute;
            bottom: 5px;
            right: 5px;
            background: rgba(52, 144, 220, 0.9);
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }

        .group-hidden { display: none; }

        /* Lightbox */
        .lightbox {
            display: none;
            position: fixed;
            z-index: 1000;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.9);
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .lightbox.active { display: flex; }

        .lightbox-content {
            max-width: 90%;
            max-height: 90%;
            position: relative;
        }

        .lightbox-content img, 
        .lightbox-content video {
            max-width: 100%;
            max-height: 85vh;
            display: block;
            border-radius: var(--border-radius);
            object-fit: contain;
        }

        .close-lightbox {
            position: fixed;
            top: 20px;
            right: 20px;
            font-size: 30px;
            color: white;
            cursor: pointer;
            z-index: 1001;
            background: rgba(0,0,0,0.5);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .lightbox-prev, 
        .lightbox-next {
            position: fixed;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0,0,0,0.7);
            color: white;
            border: none;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            font-size: 40px;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            transition: background var(--transition), transform var(--transition);
            font-weight: bold;
            z-index: 1002;
        }

        .lightbox-prev { left: 40px; }
        .lightbox-next { right: 40px; }

        .lightbox-prev:hover, 
        .lightbox-next:hover {
            background: rgba(0,0,0,0.9);
            transform: translateY(-50%) scale(1.1);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: var(--text-muted);
        }

        .empty-state h3 {
            margin-bottom: 15px;
            font-size: 24px;
        }

        .empty-state p {
            margin-bottom: 25px;
            font-size: 16px;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 20px 0;
            gap: 10px;
            flex-wrap: wrap;
        }

        .pagination-info {
            background: var(--primary-color);
            color: white;
            padding: 8px 15px;
            border-radius: var(--border-radius);
            font-size: 0.9em;
            margin: 0 10px;
            cursor: pointer;
            position: relative;
            transition: background var(--transition);
        }

        .pagination-info:hover { background: var(--primary-dark); }

        .page-selector {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            background: white;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 5px;
            z-index: 100;
            min-width: 180px;
            max-height: 300px;
            overflow-y: auto;
        }

        .page-selector.active { display: block; }

        .page-option {
            padding: 8px 12px;
            cursor: pointer;
            border-radius: 3px;
            transition: background var(--transition);
            font-size: 0.9em;
            color: #333;
        }

        .page-option:hover { background: #f0f0f0; }
        .page-option.current { background: var(--primary-color); color: white; }
        .page-option.current:hover { background: var(--primary-dark); }

        .pagination a, 
        .pagination .current {
            padding: 8px 12px;
            border: 1px solid #ddd;
            text-decoration: none;
            color: #333;
            border-radius: var(--border-radius);
            transition: background var(--transition);
        }

        .pagination a:hover { background: #f5f5f5; }
        .pagination .current {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .pagination .disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }

        .items-per-page {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0 20px;
        }

        /* Thumbnail Size Control */
        .thumbnail-size-control { margin-top: 5px; }

        .thumbnail-size-control input[type="range"] {
            width: 100%;
            height: 6px;
            background: var(--sidebar-item);
            outline: none;
            border-radius: 3px;
            -webkit-appearance: none;
            appearance: none;
        }

        .thumbnail-size-control input[type="range"]::-webkit-slider-thumb,
        .thumbnail-size-control input[type="range"]::-moz-range-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 16px;
            height: 16px;
            background: var(--primary-color);
            border-radius: 50%;
            cursor: pointer;
            border: none;
            transition: background var(--transition);
        }

        .thumbnail-size-control input[type="range"]::-webkit-slider-thumb:hover,
        .thumbnail-size-control input[type="range"]::-moz-range-thumb:hover {
            background: var(--primary-dark);
        }

        .size-labels {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 5px;
            font-size: 0.8em;
            opacity: 0.8;
        }

        #sizeValue {
            background: var(--white-alpha);
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: bold;
        }

        /* Lightbox Metadata */
        .lightbox-metadata {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 0.9em;
            max-width: 80%;
            text-align: center;
            z-index: 1003;
        }

        .metadata-basic { margin-bottom: 5px; }
        .metadata-navigation {
            font-size: 0.8em;
            opacity: 0.8;
            margin-top: 5px;
        }

        .metadata-exif {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid rgba(255,255,255,0.3);
            font-size: 0.8em;
            opacity: 0.9;
            cursor: pointer;
            transition: opacity var(--transition);
        }

        .metadata-exif:hover { opacity: 1; }

        .exif-toggle {
            background: var(--white-alpha);
            padding: 5px 10px;
            border-radius: var(--border-radius);
            text-align: center;
            user-select: none;
            transition: background var(--transition);
        }

        .exif-toggle:hover { background: rgba(255,255,255,0.2); }

        .exif-details {
            display: none;
            margin-top: 8px;
            text-align: left;
            font-size: 0.75em;
            background: rgba(0,0,0,0.6);
            padding: 8px;
            border-radius: var(--border-radius);
            max-height: 200px;
            overflow-y: auto;
        }

        .exif-details.show { display: block; }
        .exif-item { margin-bottom: 3px; }
        .arrow-icon { display: inline-block; margin: 0 3px; }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .date-gallery { grid-template-columns: repeat(auto-fit, minmax(200px, 350px)); }
        }

        @media (max-width: 900px) {
            .date-gallery { grid-template-columns: repeat(auto-fit, minmax(180px, 300px)); }
        }

        @media (max-width: 768px) {
            .lightbox-prev, .lightbox-next {
                width: 60px;
                height: 60px;
                font-size: 30px;
            }
            .lightbox-prev { left: 20px; }
            .lightbox-next { right: 20px; }
        }

        @media (max-width: 700px) {
            body { flex-direction: column; }
            .sidebar {
                width: auto;
                height: auto;
                min-width: auto;
                max-width: none;
                position: relative;
            }
            .thumbnail-size-control input[type="range"] { display: none; }
            .size-labels { justify-content: center; }
            .item {
                width: auto !important;
                height: auto !important;
                min-width: auto !important;
                min-height: auto !important;
                max-width: none !important;
                max-height: none !important;
                aspect-ratio: 1;
            }
        }

        @media (max-width: 600px) {
            .date-gallery { grid-template-columns: repeat(auto-fit, minmax(150px, 250px)); }
        }

        /* PDF Viewer Styles */
        .pdf-viewer {
            display: flex;
            flex-direction: column;
            height: 85vh;
            width: 90vw;
            max-width: 1000px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }

        .pdf-toolbar {
            background: #f0f0f0;
            padding: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #ddd;
            flex-shrink: 0;
        }

        .pdf-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .pdf-button {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.2s;
        }

        .pdf-button:hover {
            background: var(--primary-dark);
        }

        .pdf-button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .pdf-page-info {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }

        .pdf-page-input {
            width: 50px;
            padding: 4px;
            border: 1px solid #ccc;
            border-radius: 4px;
            text-align: center;
        }

        .pdf-zoom-controls {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .pdf-zoom-select {
            padding: 4px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .pdf-canvas-container {
            flex: 1;
            overflow: auto;
            background: #525659;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 20px;
        }

        .pdf-canvas {
            box-shadow: 0 4px 8px rgba(0,0,0,0.3);
            background: white;
            border-radius: 4px;
            max-width: 100%;
            height: auto;
        }

        .pdf-loading {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 200px;
            color: #666;
            font-size: 16px;
        }

        .pdf-error {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            height: 200px;
            color: #d32f2f;
            font-size: 16px;
            text-align: center;
            padding: 20px;
        }

        .pdf-error .retry-button {
            margin-top: 15px;
            background: #d32f2f;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .pdf-viewer {
                width: 95vw;
                height: 80vh;
            }
            
            .pdf-toolbar {
                flex-wrap: wrap;
                gap: 10px;
            }
            
            .pdf-controls {
                flex-wrap: wrap;
                gap: 5px;
            }
            
            .pdf-button {
                padding: 6px 10px;
                font-size: 12px;
            }
        }

        /* Audio Player Styles */
        .audio-player {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(135deg, #2d2f36, #1e1f23);
            border-top: 2px solid #1db954;
            padding: 15px 20px;
            z-index: 1000;
            transform: translateY(100%);
            transition: transform 0.3s ease;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.3);
        }

        .audio-player.active {
            transform: translateY(0);
        }

        .audio-controls {
            display: flex;
            align-items: center;
            gap: 15px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .audio-btn {
            background: none;
            border: none;
            color: white;
            font-size: 18px;
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: background 0.2s;
            min-width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .audio-btn:hover {
            background: rgba(255,255,255,0.1);
        }

        #audioPlayPause {
            background: #1db954;
            font-size: 20px;
        }

        #audioPlayPause:hover {
            background: #1ed760;
        }

        .audio-info {
            flex: 1;
            min-width: 0;
        }

        #audioTitle {
            color: white;
            font-weight: bold;
            margin-bottom: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .audio-progress-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .audio-progress {
            flex: 1;
            height: 6px;
            background: rgba(255,255,255,0.2);
            border-radius: 3px;
            cursor: pointer;
            position: relative;
        }

        .audio-progress-bar {
            height: 100%;
            background: #1db954;
            border-radius: 3px;
            width: 0%;
            transition: width 0.1s;
        }

        .audio-time {
            color: #b3b3b3;
            font-size: 12px;
            white-space: nowrap;
        }

        .audio-volume {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .volume-slider {
            width: 80px;
            height: 6px;
            background: rgba(255,255,255,0.2);
            border-radius: 3px;
            outline: none;
            cursor: pointer;
        }

        .volume-slider::-webkit-slider-thumb {
            appearance: none;
            width: 14px;
            height: 14px;
            background: #1db954;
            border-radius: 50%;
            cursor: pointer;
        }

        .volume-slider::-moz-range-thumb {
            width: 14px;
            height: 14px;
            background: #1db954;
            border-radius: 50%;
            cursor: pointer;
            border: none;
        }

        @media (max-width: 700px) {
            .audio-controls {
                gap: 10px;
                padding: 0;
            }
            
            .audio-volume {
                display: none;
            }
            
            .audio-time {
                font-size: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar with navigation and controls -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2><?= htmlspecialchars($current_dir_name) ?></h2>
        </div>
        
        <!-- Directory navigation -->
        <div class="folder-nav">
            <div class="nav-section-title">Local Folders</div>
            <?php foreach ($nav_links as $name => $path): ?>
            <a href="?dir=<?= urlencode($path) ?>&sort=<?= $actual_sort ?>&filter=<?= $filter_type ?>&per_page=<?= $items_per_page ?>" style="text-decoration: none; color: inherit;">
                <div class="folder-nav-item <?= $path == $current_dir ? 'active' : '' ?>">
                    <span class="folder-icon">📁</span>
                    <span class="group-name"><?= htmlspecialchars($name) ?></span>
                </div>
            </a>
            <?php endforeach; ?>
            
            <?php if (!empty($external_links)): ?>
            <div class="nav-section-title" style="margin-top: 20px;">External Links</div>
            <?php foreach ($external_links as $name => $url): ?>
            <a href="<?= htmlspecialchars($url) ?>" target="_blank" style="text-decoration: none; color: inherit;">
                <div class="folder-nav-item">
                    <span class="folder-icon">🔗</span>
                    <span class="group-name"><?= htmlspecialchars($name) ?></span>
                </div>
            </a>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="controls">
            <div class="group">
                <span class="group-label">Sort By</span>
                <select id="sortBy" onchange="location.href='?dir=<?= urlencode($current_dir) ?>&sort='+this.value+'&filter=<?= urlencode($filter_type) ?>&per_page=<?= $items_per_page ?>&page=1'">
                    <optgroup label="By Date Modified">
                        <option value="modified_desc" <?= $actual_sort == 'modified_desc' ? 'selected' : '' ?>>Modified - Newest First</option>
                        <option value="modified_asc" <?= $actual_sort == 'modified_asc' ? 'selected' : '' ?>>Modified - Oldest First</option>
                    </optgroup>
                    <optgroup label="By Date Taken">
                        <option value="taken_desc" <?= $actual_sort == 'taken_desc' ? 'selected' : '' ?>>Date Taken - Newest First</option>
                        <option value="taken_asc" <?= $actual_sort == 'taken_asc' ? 'selected' : '' ?>>Date Taken - Oldest First</option>
                    </optgroup>
                    <optgroup label="By Name">
                        <option value="name_asc" <?= $actual_sort == 'name_asc' ? 'selected' : '' ?>>Name (A-Z)</option>
                        <option value="name_desc" <?= $actual_sort == 'name_desc' ? 'selected' : '' ?>>Name (Z-A)</option>
                    </optgroup>
                    <optgroup label="By Size">
                        <option value="size_desc" <?= $actual_sort == 'size_desc' ? 'selected' : '' ?>>Size (Largest First)</option>
                        <option value="size_asc" <?= $actual_sort == 'size_asc' ? 'selected' : '' ?>>Size (Smallest First)</option>
                    </optgroup>
                </select>
            </div>
            
            <div class="group">
                <span class="group-label">Filter By Type</span>
                <select id="filterBy" onchange="location.href='?dir=<?= urlencode($current_dir) ?>&sort=<?= urlencode($actual_sort) ?>&filter='+this.value+'&per_page=<?= $items_per_page ?>&page=1'">
                    <option value="type_all" <?= $filter_type == 'type_all' ? 'selected' : '' ?>>All Files</option>
                    <optgroup label="By Category">
                        <option value="type_image" <?= $filter_type == 'type_image' ? 'selected' : '' ?>>All Images</option>
                        <option value="type_video" <?= $filter_type == 'type_video' ? 'selected' : '' ?>>All Videos</option>
                        <option value="type_audio" <?= $filter_type == 'type_audio' ? 'selected' : '' ?>>All Audio</option>
                        <option value="type_pdf" <?= $filter_type == 'type_pdf' ? 'selected' : '' ?>>PDF Documents</option>
                    </optgroup>
                    <optgroup label="Image Formats">
                        <option value="type_jpg" <?= $filter_type == 'type_jpg' ? 'selected' : '' ?>>JPEG Images</option>
                        <option value="type_png" <?= $filter_type == 'type_png' ? 'selected' : '' ?>>PNG Images</option>
                        <option value="type_gif" <?= $filter_type == 'type_gif' ? 'selected' : '' ?>>GIF Images</option>
                        <option value="type_webp" <?= $filter_type == 'type_webp' ? 'selected' : '' ?>>WebP Images</option>
                        <option value="type_bmp" <?= $filter_type == 'type_bmp' ? 'selected' : '' ?>>BMP Images</option>
                        <option value="type_svg" <?= $filter_type == 'type_svg' ? 'selected' : '' ?>>SVG Images</option>
                    </optgroup>
                    <optgroup label="Video Formats">
                        <option value="type_mp4" <?= $filter_type == 'type_mp4' ? 'selected' : '' ?>>MP4 Videos</option>
                        <option value="type_webm" <?= $filter_type == 'type_webm' ? 'selected' : '' ?>>WebM Videos</option>
                        <option value="type_ogg" <?= $filter_type == 'type_ogg' ? 'selected' : '' ?>>OGG Videos</option>
                        <option value="type_3gp" <?= $filter_type == 'type_3gp' ? 'selected' : '' ?>>3GP Videos</option>
                        <option value="type_mov" <?= $filter_type == 'type_mov' ? 'selected' : '' ?>>QuickTime Videos</option>
                        <option value="type_avi" <?= $filter_type == 'type_avi' ? 'selected' : '' ?>>AVI Videos</option>
                    </optgroup>
                    <optgroup label="Audio Formats">
                        <option value="type_mp3" <?= $filter_type == 'type_mp3' ? 'selected' : '' ?>>MP3 Audio</option>
                        <option value="type_opus" <?= $filter_type == 'type_opus' ? 'selected' : '' ?>>Opus Audio</option>
                        <option value="type_wav" <?= $filter_type == 'type_wav' ? 'selected' : '' ?>>WAV Audio</option>
                        <option value="type_flac" <?= $filter_type == 'type_flac' ? 'selected' : '' ?>>FLAC Audio</option>
                        <option value="type_aac" <?= $filter_type == 'type_aac' ? 'selected' : '' ?>>AAC Audio</option>
                    </optgroup>
                </select>
            </div>
            
            <div class="group">
                <span class="group-label">Thumbnail Size</span>
                <div class="thumbnail-size-control">
                    <input type="range" id="thumbnailSize" min="50" max="500" value="<?= $thumb_width ?>" 
                           oninput="updateThumbnailSize(this.value)" onchange="updateThumbnailSize(this.value)">
                    <div class="size-labels">
                        <span>Small</span>
                        <span id="sizeValue"><?= $thumb_width ?>px</span>
                        <span>Large</span>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (!empty($grouped_files)): ?>
        <div class="group-nav">
            <div class="nav-section-title">Groups</div>
            <div class="group-controls">
                <button id="expand-all">Expand All</button>
                <button id="collapse-all">Collapse All</button>
            </div>
            
            <?php foreach ($grouped_files as $group_name => $files): ?>
            <div class="group-nav-item active" data-group="<?= md5($group_name) ?>">
                <div class="group-name">
                    <?php if ($actual_sort == 'size_desc' || $actual_sort == 'size_asc'): ?>
                        <?= $group_name ?>
                    <?php elseif ($actual_sort == 'name_asc' || $actual_sort == 'name_desc'): ?>
                        <?= $group_name ?>
                    <?php elseif (strpos($filter_type, 'type_') === 0 && $filter_type !== 'type_all'): ?>
                        <?= date('M j, Y', strtotime($group_name)) ?>
                    <?php else: ?>
                        <?= date('M j, Y', strtotime($group_name)) ?>
                    <?php endif; ?>
                </div>
                <span class="badge"><?= count($files) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Main content area with the gallery -->
    <div class="content">
        <?php if (empty($grouped_files)): ?>
            <div class="empty-state">
                <h3>No media files found</h3>
                <p>There are no images or videos in this directory yet.</p>
                <p>Upload some media files to the "<?= htmlspecialchars($current_dir) ?>" directory to get started.</p>
            </div>
        <?php else: ?>
            <!-- Top pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($current_page > 1): ?>
                    <a href="?dir=<?= urlencode($current_dir) ?>&sort=<?= urlencode($actual_sort) ?>&filter=<?= urlencode($filter_type) ?>&per_page=<?= $items_per_page ?>&page=1">&laquo; First</a>
                    <a href="?dir=<?= urlencode($current_dir) ?>&sort=<?= urlencode($actual_sort) ?>&filter=<?= urlencode($filter_type) ?>&per_page=<?= $items_per_page ?>&page=<?= $current_page - 1 ?>">&lsaquo; Prev</a>
                <?php else: ?>
                    <span class="disabled">&laquo; First</span>
                    <span class="disabled">&lsaquo; Prev</span>
                <?php endif; ?>
                
                <div class="pagination-info" onclick="togglePageSelector(this)">
                    Page <?= $current_page ?> of <?= $total_pages ?> (<?= $total_files ?> items)
                    <div class="page-selector">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <div class="page-option <?= $i == $current_page ? 'current' : '' ?>" 
                                 onclick="goToPage(<?= $i ?>)">
                                Page <?= $i ?>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
                
                <div class="items-per-page">
                    <label>Items per page:</label>
                    <select onchange="location.href='?dir=<?= urlencode($current_dir) ?>&sort=<?= urlencode($actual_sort) ?>&filter=<?= urlencode($filter_type) ?>&per_page='+this.value+'&page=1'">
                        <option value="25" <?= $items_per_page == 25 ? 'selected' : '' ?>>25</option>
                        <option value="50" <?= $items_per_page == 50 ? 'selected' : '' ?>>50</option>
                        <option value="75" <?= $items_per_page == 75 ? 'selected' : '' ?>>75</option>
                        <option value="100" <?= $items_per_page == 100 ? 'selected' : '' ?>>100</option>
                        <option value="150" <?= $items_per_page == 150 ? 'selected' : '' ?>>150</option>
                        <option value="200" <?= $items_per_page == 200 ? 'selected' : '' ?>>200</option>
                        <option value="250" <?= $items_per_page == 250 ? 'selected' : '' ?>>250</option>
                        <option value="300" <?= $items_per_page == 300 ? 'selected' : '' ?>>300</option>
                        <option value="350" <?= $items_per_page == 350 ? 'selected' : '' ?>>350</option>
                        <option value="400" <?= $items_per_page == 400 ? 'selected' : '' ?>>400</option>
                        <option value="500" <?= $items_per_page == 500 ? 'selected' : '' ?>>500</option>
                    </select>
                </div>
                
                <?php if ($current_page < $total_pages): ?>
                    <a href="?dir=<?= urlencode($current_dir) ?>&sort=<?= urlencode($actual_sort) ?>&filter=<?= urlencode($filter_type) ?>&per_page=<?= $items_per_page ?>&page=<?= $current_page + 1 ?>">Next &rsaquo;</a>
                    <a href="?dir=<?= urlencode($current_dir) ?>&sort=<?= urlencode($actual_sort) ?>&filter=<?= urlencode($filter_type) ?>&per_page=<?= $items_per_page ?>&page=<?= $total_pages ?>">Last &raquo;</a>
                <?php else: ?>
                    <span class="disabled">Next &rsaquo;</span>
                    <span class="disabled">Last &raquo;</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php 
            $global_index = 0;
            foreach ($grouped_files as $group_name => $files): 
            ?>
                <div class="date-section" id="group-<?= md5($group_name) ?>">
                    <div class="date-header">
                        <?php if ($actual_sort == 'size_desc' || $actual_sort == 'size_asc'): ?>
                            <?= $group_name ?>
                        <?php elseif ($actual_sort == 'name_asc' || $actual_sort == 'name_desc'): ?>
                            <?= $group_name == '#' ? 'Other' : "Files starting with '$group_name'" ?>
                        <?php elseif (strpos($filter_type, 'type_') === 0 && $filter_type !== 'type_all'): ?>
                            <?php 
                            $type_label = '';
                            switch ($filter_type) {
                                case 'type_image': $type_label = 'Images'; break;
                                case 'type_video': $type_label = 'Videos'; break;
                                case 'type_audio': $type_label = 'Audio Files'; break;
                                case 'type_pdf': $type_label = 'PDF Documents'; break;
                                case 'type_jpg': $type_label = 'JPEG Images'; break;
                                case 'type_png': $type_label = 'PNG Images'; break;
                                case 'type_gif': $type_label = 'GIF Images'; break;
                                case 'type_webp': $type_label = 'WebP Images'; break;
                                case 'type_bmp': $type_label = 'BMP Images'; break;
                                case 'type_svg': $type_label = 'SVG Images'; break;
                                case 'type_mp4': $type_label = 'MP4 Videos'; break;
                                case 'type_webm': $type_label = 'WebM Videos'; break;
                                case 'type_ogg': $type_label = 'OGG Videos'; break;
                                case 'type_3gp': $type_label = '3GP Videos'; break;
                                case 'type_mov': $type_label = 'QuickTime Videos'; break;
                                case 'type_avi': $type_label = 'AVI Videos'; break;
                                case 'type_mp3': $type_label = 'MP3 Audio'; break;
                                case 'type_opus': $type_label = 'Opus Audio'; break;
                                case 'type_wav': $type_label = 'WAV Audio'; break;
                                case 'type_flac': $type_label = 'FLAC Audio'; break;
                                case 'type_aac': $type_label = 'AAC Audio'; break;
                            }
                            echo $type_label . ' - ' . date('l, F j, Y', strtotime($group_name));
                            ?>
                        <?php else: ?>
                            <?= date('l, F j, Y', strtotime($group_name)) ?>
                        <?php endif; ?>
                    </div>
                    <div class="date-gallery">
                        <?php foreach ($files as $file): 
                            // Only display files that can be properly JSON encoded for lightbox
                            $json_data = json_encode($file);
                            if ($json_data !== false && $json_data !== null):
                        ?>
                        <div class="item" data-index="<?= $global_index++ ?>" 
                             <?php 
                             $is_video = GalleryConfig::isVideo($file['path']);
                             $is_audio = GalleryConfig::isAudio($file['path']);
                             $is_pdf = GalleryConfig::isPDF($file['path']);
                             
                             if (!$is_audio): ?>
                                 onclick="openViewer(<?= $global_index - 1 ?>, '<?= addslashes($file['path']) ?>', '<?= $is_video ? 'video' : ($is_pdf ? 'pdf' : 'image') ?>')"
                             <?php endif; ?>>
                            <?php 
                            if ($is_video): 
                                $video_thumb_path = GalleryConfig::getVideoThumbnailPath($file['path'], $thumbs_dir);
                            ?>
                                <!-- Use img for video thumbnails to prevent Firefox from preloading videos -->
                                <img src="<?= $thumbs_dir . '/' . rawurlencode(basename($video_thumb_path)) ?>" 
                                    alt="<?= htmlspecialchars(basename($file['path'])) ?>"
                                    loading="lazy">
                                <div class="play-button">▶</div>
                            <?php elseif ($is_audio): 
                                $audio_thumb_path = GalleryConfig::getAudioThumbnailPath($file['path'], $thumbs_dir);
                                $audio_metadata = GalleryConfig::getAudioMetadata($file['path']);
                            ?>
                                <!-- Audio thumbnail with hover play functionality -->
                                <img src="<?= $thumbs_dir . '/' . rawurlencode(basename($audio_thumb_path)) ?>" 
                                    alt="<?= htmlspecialchars(basename($file['path'])) ?>"
                                    loading="lazy">
                                <div class="audio-play-button-hover" 
                                     onclick="event.stopPropagation(); playAudio('<?= addslashes($file['path']) ?>', '<?= addslashes($file['name']) ?>')">▶</div>
                            <?php elseif ($is_pdf): ?>
                                <!-- PDF thumbnail will be generated by JavaScript -->
                                <canvas class="pdf-thumbnail" 
                                    data-pdf-path="<?= htmlspecialchars($file['path']) ?>"
                                    width="200" height="260"
                                    alt="<?= htmlspecialchars(basename($file['path'])) ?>"></canvas>
                                <div class="pdf-icon">📄</div>
                            <?php else: 
                                $image_thumb_path = GalleryConfig::getImageThumbnailPath($file['path'], $thumbs_dir);
                            ?>
                                <img src="<?= $thumbs_dir . '/' . rawurlencode(basename($image_thumb_path)) ?>" 
                                    alt="<?= htmlspecialchars(basename($file['path'])) ?>"
                                    loading="lazy">
                            <?php endif; ?>
                            <div class="info">
                                <div class="info-filename"><?= htmlspecialchars($file['name']) ?></div>
                                <div style="font-size: 0.8em; margin-top: 3px;">
                                    <?php if ($is_audio): ?>
                                        Size: <?= GalleryUtils::formatFileSize($file['size']) ?><br>
                                        <?php if ($audio_metadata && isset($audio_metadata['metadata']['Duration'])): ?>
                                            Duration: <?= htmlspecialchars($audio_metadata['metadata']['Duration']) ?>
                                        <?php else: ?>
                                            Audio File
                                        <?php endif; ?>
                                    <?php else: ?>
                                        Size: <?= GalleryUtils::formatFileSize($file['size']) ?><br>
                                        Taken: <?= date('M j, Y H:i', $file['taken']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <!-- Bottom pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($current_page > 1): ?>
                    <a href="?dir=<?= urlencode($current_dir) ?>&sort=<?= urlencode($actual_sort) ?>&filter=<?= urlencode($filter_type) ?>&per_page=<?= $items_per_page ?>&page=1">&laquo; First</a>
                    <a href="?dir=<?= urlencode($current_dir) ?>&sort=<?= urlencode($actual_sort) ?>&filter=<?= urlencode($filter_type) ?>&per_page=<?= $items_per_page ?>&page=<?= $current_page - 1 ?>">&lsaquo; Prev</a>
                <?php else: ?>
                    <span class="disabled">&laquo; First</span>
                    <span class="disabled">&lsaquo; Prev</span>
                <?php endif; ?>
                
                <div class="pagination-info" onclick="togglePageSelector(this)">
                    Page <?= $current_page ?> of <?= $total_pages ?> (<?= $total_files ?> items)
                    <div class="page-selector">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <div class="page-option <?= $i == $current_page ? 'current' : '' ?>" 
                                 onclick="goToPage(<?= $i ?>)">
                                Page <?= $i ?>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
                
                <?php if ($current_page < $total_pages): ?>
                    <a href="?dir=<?= urlencode($current_dir) ?>&sort=<?= urlencode($actual_sort) ?>&filter=<?= urlencode($filter_type) ?>&per_page=<?= $items_per_page ?>&page=<?= $current_page + 1 ?>">Next &rsaquo;</a>
                    <a href="?dir=<?= urlencode($current_dir) ?>&sort=<?= urlencode($actual_sort) ?>&filter=<?= urlencode($filter_type) ?>&per_page=<?= $items_per_page ?>&page=<?= $total_pages ?>">Last &raquo;</a>
                <?php else: ?>
                    <span class="disabled">Next &rsaquo;</span>
                    <span class="disabled">Last &raquo;</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <!-- Lightbox container -->
    <div class="lightbox" id="lightbox">
        <span class="close-lightbox">&times;</span>
        <button class="lightbox-prev">&lsaquo;</button>
        <button class="lightbox-next">&rsaquo;</button>
        <div class="lightbox-content" id="lightbox-content"></div>
    </div>

    <!-- Audio Player -->
    <div id="audioPlayer" class="audio-player">
        <div class="audio-controls">
            <button id="audioPlayPause" class="audio-btn">▶</button>
            <div class="audio-info">
                <div id="audioTitle">No audio selected</div>
                <div class="audio-progress-container">
                    <div id="audioProgress" class="audio-progress">
                        <div id="audioProgressBar" class="audio-progress-bar"></div>
                    </div>
                    <div class="audio-time">
                        <span id="currentTime">0:00</span> / <span id="totalTime">0:00</span>
                    </div>
                </div>
            </div>
            <div class="audio-volume">
                <button id="audioMute" class="audio-btn">🔊</button>
                <input type="range" id="volumeSlider" min="0" max="100" value="100" class="volume-slider">
            </div>
            <button id="audioClose" class="audio-btn">✕</button>
        </div>
        <audio id="audioElement" preload="none"></audio>
    </div>

    <script>
        // Gallery Management Class - Centralized JavaScript functionality
        class InstantGallery {
            constructor() {
                this.allMediaFiles = [];
                this.currentIndex = 0;
                this.exifToggleExpanded = false;
                this.lightbox = null;
                this.lightboxContent = null;
                this.items = null;
                
                this.init();
            }
            
            init() {
                this.loadMediaFiles();
                this.initializeElements();
                this.setupEventListeners();
                this.setupURLParameters();
                this.setupThumbnailSizeControl();
            }
            
            // Media file loading
            loadMediaFiles() {
                <?php 
                $index = 0;
                foreach ($grouped_files as $group_name => $files): 
                    foreach ($files as $file): 
                        // Enhance file data with formatted fields for JavaScript
                        $enhanced_file = $file;
                        $enhanced_file['formatted_size'] = GalleryUtils::formatFileSize($file['size']);
                        $enhanced_file['formatted_modified_date'] = date('M j, Y H:i', $file['modified']);
                        $enhanced_file['formatted_taken_date'] = date('M j, Y H:i', $file['taken']);
                        
                        // Rename 'exif' to 'exif_data' for JavaScript consistency
                        if (isset($file['exif']) && $file['exif']) {
                            $enhanced_file['exif_data'] = $file['exif'];
                            // Debug: Log EXIF data transfer
                            error_log("Transferring EXIF data for {$file['name']}: " . print_r($file['exif'], true));
                        } else {
                            error_log("No EXIF data to transfer for {$file['name']}");
                        }
                        unset($enhanced_file['exif']); // Remove old field
                        
                        // Only include files that can be properly JSON encoded (matching HTML generation logic)
                        $json_data = json_encode($enhanced_file);
                        if ($json_data !== false && $json_data !== null):
                ?>
                    this.allMediaFiles[<?= $index++ ?>] = <?= $json_data ?>;
                <?php 
                        endif;
                    endforeach; 
                endforeach; 
                ?>
            }
            
            // Initialize DOM elements
            initializeElements() {
                this.lightbox = document.getElementById('lightbox');
                this.lightboxContent = document.getElementById('lightbox-content');
                this.closeButton = document.querySelector('.close-lightbox');
                this.prevButton = document.querySelector('.lightbox-prev');
                this.nextButton = document.querySelector('.lightbox-next');
                this.items = document.querySelectorAll('.item');
            }
            
            // Setup URL parameters
            setupURLParameters() {
                const urlParams = new URLSearchParams(window.location.search);
                const sortBy = urlParams.get('sort');
                if (sortBy) {
                    // Handle sorting options
                    if (sortBy.startsWith('modified_') || sortBy.startsWith('taken_') || 
                        sortBy.startsWith('name_') || sortBy === 'size_') {
                        document.getElementById('sortBy').value = sortBy;
                    }
                    
                    // Handle filter options
                    if (sortBy.startsWith('type_')) {
                        document.getElementById('filterBy').value = sortBy;
                    }
                }
            }
            
            // Video handling utilities
            createVideoElement(file) {
                const video = document.createElement('video');
                video.controls = true;
                video.preload = 'metadata';
                video.style.maxWidth = '100%';
                video.style.maxHeight = '85vh';
                
                console.log('Creating video element for:', file.name);
                
                // Firefox-specific handling
                const isFirefox = navigator.userAgent.includes('Firefox');
                if (isFirefox) {
                    console.log('Firefox detected, applying specific handling');
                    video.preload = 'auto';
                }
                
                // Add error handling
                video.onerror = (e) => {
                    console.error('Video failed to load:', file.path, 'Error:', e);
                    this.createVideoFallback(file);
                };
                
                // Add load events for debugging
                video.onloadstart = () => console.log('Video load started:', file.name);
                video.oncanplay = () => console.log('Video can play:', file.name);
                
                // Create source element
                const source = document.createElement('source');
                const normalizedPath = file.path.replace(/\\/g, '/');
                const pathParts = normalizedPath.split('/');
                const encodedFilename = encodeURIComponent(pathParts.pop());
                const pathDir = pathParts.join('/');
                source.src = pathDir + '/' + encodedFilename;
                source.type = file.type;
                
                console.log('Video source URL:', source.src, 'Type:', source.type);
                
                source.onerror = (e) => console.error('Video source failed to load:', source.src, 'Error:', e);
                
                video.appendChild(source);
                
                // Add fallback text
                const fallbackText = document.createElement('p');
                fallbackText.style.color = 'white';
                fallbackText.innerHTML = `Your browser does not support the video format. <a href="${source.src}" target="_blank" style="color: #3498db;">Download the video</a> to view it.`;
                video.appendChild(fallbackText);
                
                return video;
            }
            
            createVideoFallback(file) {
                const fallback = document.createElement('div');
                fallback.style.color = 'white';
                fallback.style.textAlign = 'center';
                fallback.style.padding = '50px';
                fallback.innerHTML = `
                    <h3>Video could not be loaded</h3>
                    <p>File: ${file.name}</p>
                    <p>Type: ${file.type}</p>
                    <p>Browser: ${navigator.userAgent.includes('Firefox') ? 'Firefox' : 'Other'}</p>
                    <a href="${file.path}" target="_blank" style="color: #3498db;">Download Video</a>
                `;
                this.lightboxContent.innerHTML = '';
                this.lightboxContent.appendChild(fallback);
            }
            
            // Image handling utilities
            createImageElement(file) {
                const img = document.createElement('img');
                const normalizedPath = file.path.replace(/\\/g, '/');
                const pathParts = normalizedPath.split('/');
                const encodedFilename = encodeURIComponent(pathParts.pop());
                const pathDir = pathParts.join('/');
                img.src = pathDir + '/' + encodedFilename;
                img.alt = normalizedPath.split('/').pop();
                return img;
            }

            // PDF handling utilities
            createPDFElement(file) {
                const pdfViewer = document.createElement('div');
                pdfViewer.className = 'pdf-viewer';

                // Create toolbar
                const toolbar = document.createElement('div');
                toolbar.className = 'pdf-toolbar';

                // Page controls
                const pageControls = document.createElement('div');
                pageControls.className = 'pdf-controls';

                const prevBtn = document.createElement('button');
                prevBtn.className = 'pdf-button';
                prevBtn.textContent = '← Previous';
                prevBtn.disabled = true;

                const nextBtn = document.createElement('button');
                nextBtn.className = 'pdf-button';
                nextBtn.textContent = 'Next →';

                const pageInfo = document.createElement('div');
                pageInfo.className = 'pdf-page-info';

                const pageInput = document.createElement('input');
                pageInput.type = 'number';
                pageInput.className = 'pdf-page-input';
                pageInput.value = '1';
                pageInput.min = '1';

                const pageTotal = document.createElement('span');
                pageTotal.textContent = '/ 1';

                pageInfo.appendChild(document.createTextNode('Page '));
                pageInfo.appendChild(pageInput);
                pageInfo.appendChild(pageTotal);

                pageControls.appendChild(prevBtn);
                pageControls.appendChild(nextBtn);
                pageControls.appendChild(pageInfo);

                // Zoom controls
                const zoomControls = document.createElement('div');
                zoomControls.className = 'pdf-zoom-controls';

                const zoomOut = document.createElement('button');
                zoomOut.className = 'pdf-button';
                zoomOut.textContent = '−';

                const zoomSelect = document.createElement('select');
                zoomSelect.className = 'pdf-zoom-select';
                ['50%', '75%', '100%', '125%', '150%', '200%', 'Fit Width', 'Fit Page'].forEach(zoom => {
                    const option = document.createElement('option');
                    option.value = zoom;
                    option.textContent = zoom;
                    if (zoom === '100%') option.selected = true;
                    zoomSelect.appendChild(option);
                });

                const zoomIn = document.createElement('button');
                zoomIn.className = 'pdf-button';
                zoomIn.textContent = '+';

                zoomControls.appendChild(zoomOut);
                zoomControls.appendChild(zoomSelect);
                zoomControls.appendChild(zoomIn);

                toolbar.appendChild(pageControls);
                toolbar.appendChild(zoomControls);

                // Canvas container
                const canvasContainer = document.createElement('div');
                canvasContainer.className = 'pdf-canvas-container';

                const loadingDiv = document.createElement('div');
                loadingDiv.className = 'pdf-loading';
                loadingDiv.textContent = 'Loading PDF...';
                canvasContainer.appendChild(loadingDiv);

                pdfViewer.appendChild(toolbar);
                pdfViewer.appendChild(canvasContainer);

                // Initialize PDF.js
                this.initializePDF(file, canvasContainer, prevBtn, nextBtn, pageInput, pageTotal, zoomSelect, zoomOut, zoomIn);

                return pdfViewer;
            }

            async initializePDF(file, container, prevBtn, nextBtn, pageInput, pageTotal, zoomSelect, zoomOut, zoomIn) {
                try {
                    // Configure PDF.js worker
                    if (typeof pdfjsLib !== 'undefined') {
                        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
                    }

                    const normalizedPath = file.path.replace(/\\/g, '/');
                    const pathParts = normalizedPath.split('/');
                    const encodedFilename = encodeURIComponent(pathParts.pop());
                    const pathDir = pathParts.join('/');
                    const pdfUrl = pathDir + '/' + encodedFilename;

                    console.log('Loading PDF:', pdfUrl);

                    const pdf = await pdfjsLib.getDocument(pdfUrl).promise;
                    
                    let currentPage = 1;
                    let currentScale = 1.0;
                    let canvas = null;
                    let renderingPage = false;

                    // Update page info
                    pageTotal.textContent = `/ ${pdf.numPages}`;
                    pageInput.max = pdf.numPages;

                    // Function to render a page
                    const renderPage = async (pageNum, scale = currentScale) => {
                        if (renderingPage) return;
                        renderingPage = true;

                        try {
                            const page = await pdf.getPage(pageNum);
                            
                            // Create or reuse canvas
                            if (!canvas) {
                                canvas = document.createElement('canvas');
                                canvas.className = 'pdf-canvas';
                                container.innerHTML = '';
                                container.appendChild(canvas);
                            }

                            const context = canvas.getContext('2d');
                            
                            // Calculate scale based on zoom selection
                            let viewport;
                            if (zoomSelect.value === 'Fit Width') {
                                const containerWidth = container.clientWidth - 40; // Account for padding
                                const pageViewport = page.getViewport({ scale: 1.0 });
                                scale = containerWidth / pageViewport.width;
                                viewport = page.getViewport({ scale });
                            } else if (zoomSelect.value === 'Fit Page') {
                                const containerWidth = container.clientWidth - 40;
                                const containerHeight = container.clientHeight - 40;
                                const pageViewport = page.getViewport({ scale: 1.0 });
                                const scaleX = containerWidth / pageViewport.width;
                                const scaleY = containerHeight / pageViewport.height;
                                scale = Math.min(scaleX, scaleY);
                                viewport = page.getViewport({ scale });
                            } else {
                                const zoomPercent = parseInt(zoomSelect.value) || 100;
                                scale = zoomPercent / 100;
                                viewport = page.getViewport({ scale });
                            }

                            currentScale = scale;

                            // Set canvas dimensions
                            canvas.width = viewport.width;
                            canvas.height = viewport.height;

                            // Render the page
                            const renderContext = {
                                canvasContext: context,
                                viewport: viewport
                            };

                            await page.render(renderContext).promise;
                            
                            // Update controls
                            prevBtn.disabled = pageNum <= 1;
                            nextBtn.disabled = pageNum >= pdf.numPages;
                            pageInput.value = pageNum;
                            currentPage = pageNum;

                            console.log(`Rendered PDF page ${pageNum}/${pdf.numPages}`);

                        } catch (error) {
                            console.error('Error rendering PDF page:', error);
                            this.showPDFError(container, 'Failed to render PDF page');
                        } finally {
                            renderingPage = false;
                        }
                    };

                    // Event listeners
                    prevBtn.addEventListener('click', () => {
                        if (currentPage > 1) {
                            renderPage(currentPage - 1);
                        }
                    });

                    nextBtn.addEventListener('click', () => {
                        if (currentPage < pdf.numPages) {
                            renderPage(currentPage + 1);
                        }
                    });

                    pageInput.addEventListener('change', () => {
                        const pageNum = parseInt(pageInput.value);
                        if (pageNum >= 1 && pageNum <= pdf.numPages) {
                            renderPage(pageNum);
                        } else {
                            pageInput.value = currentPage;
                        }
                    });

                    zoomSelect.addEventListener('change', () => {
                        renderPage(currentPage);
                    });

                    zoomOut.addEventListener('click', () => {
                        const currentZoom = parseInt(zoomSelect.value) || 100;
                        const newZoom = Math.max(25, currentZoom - 25);
                        zoomSelect.value = newZoom + '%';
                        renderPage(currentPage);
                    });

                    zoomIn.addEventListener('click', () => {
                        const currentZoom = parseInt(zoomSelect.value) || 100;
                        const newZoom = Math.min(300, currentZoom + 25);
                        zoomSelect.value = newZoom + '%';
                        renderPage(currentPage);
                    });

                    // Render first page
                    await renderPage(1);

                } catch (error) {
                    console.error('Error loading PDF:', error);
                    this.showPDFError(container, 'Failed to load PDF: ' + error.message, file);
                }
            }

            showPDFError(container, message, file = null) {
                container.innerHTML = '';
                const errorDiv = document.createElement('div');
                errorDiv.className = 'pdf-error';
                
                const errorMessage = document.createElement('div');
                errorMessage.textContent = message;
                errorDiv.appendChild(errorMessage);

                if (file) {
                    const downloadBtn = document.createElement('button');
                    downloadBtn.className = 'retry-button';
                    downloadBtn.textContent = 'Download PDF';
                    downloadBtn.addEventListener('click', () => {
                        const normalizedPath = file.path.replace(/\\/g, '/');
                        const pathParts = normalizedPath.split('/');
                        const encodedFilename = encodeURIComponent(pathParts.pop());
                        const pathDir = pathParts.join('/');
                        const downloadUrl = pathDir + '/' + encodedFilename;
                        window.open(downloadUrl, '_blank');
                    });
                    errorDiv.appendChild(downloadBtn);
                }

                container.appendChild(errorDiv);
            }
            
            // Metadata creation utilities
            createMetadata(file) {
                console.log('Creating metadata for file:', file.name);
                console.log('EXIF data available:', !!file.exif_data);
                console.log('EXIF data content:', file.exif_data);
                
                const metadataDiv = document.createElement('div');
                metadataDiv.className = 'lightbox-metadata';
                
                // Basic metadata
                const filename = file.path.split('/').pop().split('\\').pop();
                const basicMetadata = document.createElement('div');
                basicMetadata.className = 'metadata-basic';
                basicMetadata.innerHTML = `
                    <span class="filename">${filename}</span>
                    <span class="filesize">${file.formatted_size || file.size}</span>
                    ${file.formatted_modified_date ? `<span class="modified-date">Modified: ${file.formatted_modified_date}</span>` : ''}
                    ${file.formatted_taken_date ? `<span class="taken-date filesize">Taken: ${file.formatted_taken_date}</span>` : ''}
                `;
                metadataDiv.appendChild(basicMetadata);
                
                // Navigation instructions
                const navInstructions = document.createElement('div');
                navInstructions.className = 'metadata-navigation';
                navInstructions.innerHTML = 'Use ← → arrow keys or swipe left/right to navigate';
                metadataDiv.appendChild(navInstructions);
                
                // EXIF data (if available)
                if (file.exif_data && Object.keys(file.exif_data).length > 0) {
                    const exifDiv = this.createExifSection(file.exif_data);
                    metadataDiv.appendChild(exifDiv);
                } else {
                    console.log('No EXIF data found or EXIF data is empty');
                }
                
                return metadataDiv;
            }
            
            createExifSection(exifData) {
                const exifDiv = document.createElement('div');
                exifDiv.className = 'metadata-exif';
                
                const exifToggle = document.createElement('div');
                exifToggle.className = 'exif-toggle';
                exifToggle.innerHTML = this.exifToggleExpanded ? 'EXIF Data ▲' : 'EXIF Data ▼';
                exifToggle.addEventListener('click', () => {
                    const exifDetails = exifDiv.querySelector('.exif-details');
                    const isCurrentlyExpanded = exifDetails.style.display !== 'none';
                    exifDetails.style.display = isCurrentlyExpanded ? 'none' : 'block';
                    exifToggle.innerHTML = isCurrentlyExpanded ? 'EXIF Data ▼' : 'EXIF Data ▲';
                    this.exifToggleExpanded = !isCurrentlyExpanded;
                });
                
                const exifDetails = document.createElement('div');
                exifDetails.className = 'exif-details';
                exifDetails.style.display = this.exifToggleExpanded ? 'block' : 'none';
                
                // Format EXIF data
                let exifContent = '';
                for (const [key, value] of Object.entries(exifData)) {
                    if (value && value !== '' && value !== 'Unknown') {
                        exifContent += `<div class="exif-item"><strong>${key}:</strong> ${value}</div>`;
                    }
                }
                exifDetails.innerHTML = exifContent;
                
                exifDiv.appendChild(exifToggle);
                exifDiv.appendChild(exifDetails);
                
                return exifDiv;
            }
            
            // Main lightbox display function
            showMedia(index) {
                // Make sure index is within bounds
                if (index < 0) index = this.allMediaFiles.length - 1;
                if (index >= this.allMediaFiles.length) index = 0;
                
                this.currentIndex = index;
                this.lightboxContent.innerHTML = '';
                
                const file = this.allMediaFiles[index];
                console.log('Displaying file:', file.name, 'Type:', file.type, 'Browser:', navigator.userAgent.includes('Firefox') ? 'Firefox' : 'Other');
                
                // Create appropriate media element
                if (file.type && file.type.toString().includes('video')) {
                    const video = this.createVideoElement(file);
                    this.lightboxContent.appendChild(video);
                } else if (file.type && file.type.toString().includes('application/pdf')) {
                    // Handle PDF files specially
                    const pdfViewer = this.createPDFElement(file);
                    this.lightboxContent.appendChild(pdfViewer);
                    // Don't add metadata for PDFs as they have their own toolbar
                    return;
                } else {
                    const img = this.createImageElement(file);
                    this.lightboxContent.appendChild(img);
                }
                
                // Add metadata (for non-PDF files)
                const metadata = this.createMetadata(file);
                this.lightboxContent.appendChild(metadata);
            }
            
            // Navigation methods
            showPrevious() {
                this.showMedia(this.currentIndex - 1);
            }
            
            showNext() {
                this.showMedia(this.currentIndex + 1);
            }
            
            openLightbox(index) {
                this.currentIndex = parseInt(index);
                this.exifToggleExpanded = false; // Reset EXIF toggle state
                this.showMedia(this.currentIndex);
                this.lightbox.classList.add('active');
            }
            
            closeLightbox() {
                this.lightbox.classList.remove('active');
                
                // Pause any videos when closing lightbox
                const video = this.lightboxContent.querySelector('video');
                if (video) {
                    video.pause();
                }
            }
            
            // Event listener setup
            setupEventListeners() {
                // Lightbox navigation
                this.prevButton.addEventListener('click', () => this.showPrevious());
                this.nextButton.addEventListener('click', () => this.showNext());
                this.closeButton.addEventListener('click', () => this.closeLightbox());
                
                // Open lightbox when clicking an item
                this.items.forEach(item => {
                    item.addEventListener('click', () => {
                        const index = item.getAttribute('data-index');
                        this.openLightbox(index);
                    });
                });
                
                // Close lightbox when clicking outside content
                this.lightbox.addEventListener('click', (e) => {
                    if (e.target === this.lightbox) {
                        this.closeLightbox();
                    }
                });
                
                // Touch/swipe support
                this.setupTouchEvents();
                
                // Keyboard navigation
                this.setupKeyboardEvents();
                
                // Group navigation
                this.setupGroupNavigation();
            }
            
            // Touch/swipe event handling
            setupTouchEvents() {
                let touchStartX = 0, touchStartY = 0, touchEndX = 0, touchEndY = 0;
                
                this.lightboxContent.addEventListener('touchstart', (e) => {
                    touchStartX = e.changedTouches[0].screenX;
                    touchStartY = e.changedTouches[0].screenY;
                });
                
                this.lightboxContent.addEventListener('touchend', (e) => {
                    touchEndX = e.changedTouches[0].screenX;
                    touchEndY = e.changedTouches[0].screenY;
                    
                    const deltaX = touchEndX - touchStartX;
                    const deltaY = touchEndY - touchStartY;
                    
                    // Check if it's a horizontal swipe
                    if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > 50) {
                        if (deltaX > 0) {
                            this.showPrevious(); // Swipe right - previous
                        } else {
                            this.showNext(); // Swipe left - next
                        }
                        e.preventDefault();
                    } else if (Math.abs(deltaX) < 30 && Math.abs(deltaY) < 30) {
                        // Small movement, treat as tap to close
                        this.closeLightbox();
                    }
                });
                
                // Prevent default touch behavior
                this.lightboxContent.addEventListener('touchmove', (e) => e.preventDefault());
            }
            
            // Keyboard event handling
            setupKeyboardEvents() {
                document.addEventListener('keydown', (e) => {
                    if (this.lightbox.classList.contains('active')) {
                        // Check if we're viewing a PDF
                        const pdfViewer = this.lightboxContent.querySelector('.pdf-viewer');
                        const activeElement = document.activeElement;
                        
                        // If PDF is active and user is typing in input field, don't interfere
                        if (pdfViewer && (activeElement.tagName === 'INPUT' || activeElement.tagName === 'SELECT')) {
                            return;
                        }
                        
                        switch (e.key) {
                            case 'Escape':
                                this.closeLightbox();
                                break;
                            case 'ArrowLeft':
                                if (pdfViewer) {
                                    // For PDFs, left arrow navigates to previous page
                                    const prevBtn = pdfViewer.querySelector('.pdf-button');
                                    if (prevBtn && !prevBtn.disabled) {
                                        prevBtn.click();
                                    }
                                } else {
                                    // For images/videos, navigate to previous file
                                    this.showPrevious();
                                }
                                break;
                            case 'ArrowRight':
                                if (pdfViewer) {
                                    // For PDFs, right arrow navigates to next page
                                    const nextBtn = pdfViewer.querySelectorAll('.pdf-button')[1];
                                    if (nextBtn && !nextBtn.disabled) {
                                        nextBtn.click();
                                    }
                                } else {
                                    // For images/videos, navigate to next file
                                    this.showNext();
                                }
                                break;
                            case 'ArrowUp':
                                if (pdfViewer) {
                                    // For PDFs, up arrow changes to previous file
                                    this.showPrevious();
                                }
                                break;
                            case 'ArrowDown':
                                if (pdfViewer) {
                                    // For PDFs, down arrow changes to next file
                                    this.showNext();
                                }
                                break;
                            case '+':
                            case '=':
                                if (pdfViewer) {
                                    // Zoom in on PDF
                                    const zoomIn = pdfViewer.querySelector('.pdf-zoom-controls .pdf-button:last-child');
                                    if (zoomIn) zoomIn.click();
                                }
                                break;
                            case '-':
                                if (pdfViewer) {
                                    // Zoom out on PDF
                                    const zoomOut = pdfViewer.querySelector('.pdf-zoom-controls .pdf-button:first-child');
                                    if (zoomOut) zoomOut.click();
                                }
                                break;
                        }
                    }
                });
            }
            
            // Group navigation setup
            setupGroupNavigation() {
                const groupNavItems = document.querySelectorAll('.group-nav-item');
                const expandAllBtn = document.getElementById('expand-all');
                const collapseAllBtn = document.getElementById('collapse-all');
                
                groupNavItems.forEach(item => {
                    item.addEventListener('click', () => {
                        const groupId = item.getAttribute('data-group');
                        const groupItems = document.getElementById('group-' + groupId);
                        
                        // Toggle visibility
                        if (item.classList.contains('active')) {
                            item.classList.remove('active');
                            groupItems.classList.add('group-hidden');
                        } else {
                            item.classList.add('active');
                            groupItems.classList.remove('group-hidden');
                        }
                        
                        // Scroll to the group if it's being shown
                        if (!groupItems.classList.contains('group-hidden')) {
                            groupItems.scrollIntoView({ behavior: 'smooth' });
                        }
                    });
                });
                
                // Handle expand/collapse all functionality
                if (expandAllBtn && collapseAllBtn) {
                    expandAllBtn.addEventListener('click', () => {
                        groupNavItems.forEach(item => {
                            item.classList.add('active');
                            const groupId = item.getAttribute('data-group');
                            const groupItems = document.getElementById('group-' + groupId);
                            groupItems.classList.remove('group-hidden');
                        });
                    });
                    
                    collapseAllBtn.addEventListener('click', () => {
                        groupNavItems.forEach(item => {
                            item.classList.remove('active');
                            const groupId = item.getAttribute('data-group');
                            const groupItems = document.getElementById('group-' + groupId);
                            groupItems.classList.add('group-hidden');
                        });
                    });
                }
            }
            
            // Thumbnail size control
            setupThumbnailSizeControl() {
                const slider = document.getElementById('thumbnailSize');
                if (slider) {
                    // Load saved thumbnail size
                    const savedSize = localStorage.getItem('thumbnailSize');
                    if (savedSize) {
                        slider.value = savedSize;
                    }
                    
                    // Apply initial size
                    this.updateThumbnailSize(slider.value);
                    
                    // Setup resize handler
                    let resizeTimeout;
                    window.addEventListener('resize', () => {
                        clearTimeout(resizeTimeout);
                        resizeTimeout = setTimeout(() => this.updateThumbnailSize(slider.value), 250);
                    });
                    
                    // Apply size after short delay to ensure all elements are loaded
                    setTimeout(() => this.updateThumbnailSize(slider.value), 100);
                }
            }
            
            updateThumbnailSize(size) {
                const sizeValue = document.getElementById('sizeValue');
                if (sizeValue) {
                    sizeValue.textContent = size + 'px';
                }
                
                // Don't apply dynamic sizing on mobile devices
                if (window.innerWidth <= 700) {
                    localStorage.setItem('thumbnailSize', size);
                    return;
                }
                
                // Calculate max size to prevent excessive stretching
                const maxSize = Math.min(parseInt(size) * 1.5, 400);
                
                // Update gallery grids
                const galleries = document.querySelectorAll('.date-gallery');
                galleries.forEach(gallery => {
                    gallery.style.gridTemplateColumns = `repeat(auto-fit, minmax(${size}px, ${maxSize}px))`;
                    gallery.style.justifyContent = 'start';
                });
                
                // Update thumbnail items
                const items = document.querySelectorAll('.item');
                items.forEach(item => {
                    item.style.minWidth = size + 'px';
                    item.style.minHeight = size + 'px';
                    item.style.maxWidth = maxSize + 'px';
                    item.style.maxHeight = maxSize + 'px';
                    item.style.width = 'auto';
                    item.style.height = 'auto';
                    item.style.aspectRatio = '1';
                });
                
                // Store preference
                localStorage.setItem('thumbnailSize', size);
            }
        }
        
        // Utility functions for backward compatibility
        function updateThumbnailSize(size) {
            if (window.gallery) {
                window.gallery.updateThumbnailSize(size);
            }
        }
        
        function togglePageSelector(element) {
            const selector = element.querySelector('.page-selector');
            const allSelectors = document.querySelectorAll('.page-selector');
            
            // Close all other selectors
            allSelectors.forEach(sel => {
                if (sel !== selector) {
                    sel.classList.remove('active');
                }
            });
            
            // Toggle this selector
            selector.classList.toggle('active');
        }
        
        function goToPage(pageNumber) {
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.set('page', pageNumber);
            window.location.href = currentUrl.toString();
        }
        
        // PDF thumbnail generation using PDF.js
        async function generatePDFThumbnails() {
            if (typeof pdfjsLib === 'undefined') {
                console.warn('PDF.js not loaded, skipping PDF thumbnail generation');
                return;
            }

            // Find all PDF canvas elements
            const pdfCanvases = document.querySelectorAll('.pdf-thumbnail');
            
            for (const canvas of pdfCanvases) {
                try {
                    // Check if already processed
                    if (canvas.dataset.pdfProcessed === 'true') continue;
                    
                    const pdfPath = canvas.dataset.pdfPath;
                    if (!pdfPath) continue;
                    
                    console.log('Generating PDF thumbnail for:', pdfPath);
                    
                    // First, draw a generic placeholder
                    const context = canvas.getContext('2d');
                    const canvasWidth = canvas.width;
                    const canvasHeight = canvas.height;
                    
                    // Draw placeholder background
                    context.fillStyle = '#f8f9fa';
                    context.fillRect(0, 0, canvasWidth, canvasHeight);
                    
                    // Draw border
                    context.strokeStyle = '#3498db';
                    context.lineWidth = 2;
                    context.strokeRect(1, 1, canvasWidth - 2, canvasHeight - 2);
                    
                    // Draw "PDF" text
                    context.fillStyle = '#3498db';
                    context.font = 'bold 24px Arial';
                    context.textAlign = 'center';
                    context.fillText('PDF', canvasWidth / 2, canvasHeight / 2 - 10);
                    
                    // Draw "Loading..." text
                    context.fillStyle = '#6c757d';
                    context.font = '12px Arial';
                    context.fillText('Loading...', canvasWidth / 2, canvasHeight / 2 + 20);
                    
                    // Load the PDF and render the first page
                    try {
                        const loadingTask = pdfjsLib.getDocument(pdfPath);
                        const pdf = await loadingTask.promise;
                        
                        // Get the first page
                        const page = await pdf.getPage(1);
                        
                        // Calculate scale to fit the canvas while maintaining aspect ratio
                        const viewport = page.getViewport({ scale: 1 });
                        const scaleX = canvasWidth / viewport.width;
                        const scaleY = canvasHeight / viewport.height;
                        const scale = Math.min(scaleX, scaleY);
                        
                        const scaledViewport = page.getViewport({ scale });
                        
                        // Clear the canvas
                        context.clearRect(0, 0, canvasWidth, canvasHeight);
                        
                        // Center the PDF page in the canvas
                        const offsetX = (canvasWidth - scaledViewport.width) / 2;
                        const offsetY = (canvasHeight - scaledViewport.height) / 2;
                        
                        // Fill background with white
                        context.fillStyle = 'white';
                        context.fillRect(offsetX, offsetY, scaledViewport.width, scaledViewport.height);
                        
                        // Render the page
                        const renderContext = {
                            canvasContext: context,
                            viewport: scaledViewport,
                            transform: [1, 0, 0, 1, offsetX, offsetY]
                        };
                        
                        await page.render(renderContext).promise;
                        
                        console.log('PDF thumbnail generated successfully for:', pdfPath);
                        
                    } catch (pdfError) {
                        console.warn('Failed to render PDF page, keeping placeholder:', pdfError);
                        // Keep the placeholder that was already drawn
                    }
                    
                    // Mark as processed
                    canvas.dataset.pdfProcessed = 'true';
                    
                } catch (error) {
                    console.warn('Failed to generate PDF thumbnail:', error);
                }
            }
        }

        // Audio Player Management
        class AudioPlayer {
            constructor() {
                this.player = document.getElementById('audioPlayer');
                this.audioElement = document.getElementById('audioElement');
                this.playPauseBtn = document.getElementById('audioPlayPause');
                this.titleElement = document.getElementById('audioTitle');
                this.progressBar = document.getElementById('audioProgressBar');
                this.progressContainer = document.getElementById('audioProgress');
                this.currentTimeElement = document.getElementById('currentTime');
                this.totalTimeElement = document.getElementById('totalTime');
                this.muteBtn = document.getElementById('audioMute');
                this.volumeSlider = document.getElementById('volumeSlider');
                this.closeBtn = document.getElementById('audioClose');
                
                this.currentAudioPath = null;
                this.currentAudioTitle = null;
                this.isPlaying = false;
                
                this.init();
            }
            
            init() {
                // Audio element events
                this.audioElement.addEventListener('loadedmetadata', () => {
                    this.updateTotalTime();
                });
                
                this.audioElement.addEventListener('timeupdate', () => {
                    this.updateProgress();
                });
                
                this.audioElement.addEventListener('ended', () => {
                    this.pause();
                });
                
                // Control events
                this.playPauseBtn.addEventListener('click', () => {
                    this.togglePlayPause();
                });
                
                this.progressContainer.addEventListener('click', (e) => {
                    this.seekTo(e);
                });
                
                this.muteBtn.addEventListener('click', () => {
                    this.toggleMute();
                });
                
                this.volumeSlider.addEventListener('input', (e) => {
                    this.setVolume(e.target.value / 100);
                });
                
                this.closeBtn.addEventListener('click', () => {
                    this.close();
                });
            }
            
            load(audioPath, title) {
                this.currentAudioPath = audioPath;
                this.currentAudioTitle = title;
                
                // Update UI
                this.titleElement.textContent = title;
                this.audioElement.src = audioPath;
                
                // Show player
                this.show();
                
                // Clear previous audio indicators
                this.clearAudioIndicators();
                
                console.log('Loading audio:', audioPath);
            }
            
            show() {
                this.player.classList.add('active');
            }
            
            hide() {
                this.player.classList.remove('active');
            }
            
            close() {
                this.pause();
                this.hide();
                this.clearAudioIndicators();
                this.currentAudioPath = null;
                this.currentAudioTitle = null;
                this.titleElement.textContent = 'No audio selected';
            }
            
            play() {
                this.audioElement.play().then(() => {
                    this.isPlaying = true;
                    this.playPauseBtn.textContent = '⏸';
                    this.updateAudioIndicators();
                }).catch(error => {
                    console.error('Error playing audio:', error);
                });
            }
            
            pause() {
                this.audioElement.pause();
                this.isPlaying = false;
                this.playPauseBtn.textContent = '▶';
                this.clearAudioIndicators();
            }
            
            togglePlayPause() {
                if (this.isPlaying) {
                    this.pause();
                } else {
                    this.play();
                }
            }
            
            updateProgress() {
                if (this.audioElement.duration) {
                    const progress = (this.audioElement.currentTime / this.audioElement.duration) * 100;
                    this.progressBar.style.width = progress + '%';
                    this.currentTimeElement.textContent = this.formatTime(this.audioElement.currentTime);
                }
            }
            
            updateTotalTime() {
                if (this.audioElement.duration) {
                    this.totalTimeElement.textContent = this.formatTime(this.audioElement.duration);
                }
            }
            
            seekTo(event) {
                const rect = this.progressContainer.getBoundingClientRect();
                const percent = (event.clientX - rect.left) / rect.width;
                const time = percent * this.audioElement.duration;
                this.audioElement.currentTime = time;
            }
            
            toggleMute() {
                if (this.audioElement.muted) {
                    this.audioElement.muted = false;
                    this.muteBtn.textContent = '🔊';
                    this.volumeSlider.value = this.audioElement.volume * 100;
                } else {
                    this.audioElement.muted = true;
                    this.muteBtn.textContent = '🔇';
                }
            }
            
            setVolume(volume) {
                this.audioElement.volume = volume;
                this.audioElement.muted = false;
                this.muteBtn.textContent = volume > 0 ? '🔊' : '🔇';
            }
            
            formatTime(seconds) {
                const minutes = Math.floor(seconds / 60);
                const remainingSeconds = Math.floor(seconds % 60);
                return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
            }
            
            updateAudioIndicators() {
                // Find the current audio item and mark it as playing
                const audioItems = document.querySelectorAll('.item');
                audioItems.forEach(item => {
                    const audioPath = this.getAudioPathFromOnclick(item.getAttribute('onclick'));
                    if (audioPath === this.currentAudioPath) {
                        item.setAttribute('data-audio-playing', 'true');
                    }
                });
            }
            
            clearAudioIndicators() {
                // Clear all audio playing indicators
                const audioItems = document.querySelectorAll('.item[data-audio-playing="true"]');
                audioItems.forEach(item => {
                    item.removeAttribute('data-audio-playing');
                });
            }
            
            getAudioPathFromOnclick(onclickStr) {
                if (!onclickStr) return null;
                const match = onclickStr.match(/playAudio\(['"]([^'"]+)['"]/);
                return match ? match[1] : null;
            }
        }

        // Global audio player instance
        window.audioPlayer = new AudioPlayer();

        // Global function to play audio (called from HTML onclick)
        function playAudio(audioPath, title) {
            window.audioPlayer.load(audioPath, title);
            window.audioPlayer.play();
        }

        // Initialize gallery when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            window.gallery = new InstantGallery();
            
            // Generate PDF thumbnails after initial load
            setTimeout(generatePDFThumbnails, 500);
            
            // Close page selector when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.pagination-info')) {
                    document.querySelectorAll('.page-selector.active').forEach(selector => {
                        selector.classList.remove('active');
                    });
                }
            });
            
            // Prevent page selector from closing when clicking on page options
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('page-option')) {
                    e.stopPropagation();
                }
            });
        });
    </script>
</body>
</html>