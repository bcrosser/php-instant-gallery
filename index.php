<?php
// Configuration
$base_dir = './pics';  // Base directory containing media files
$thumbs_dir = './thumbs';  // Directory for generated thumbnails
$thumb_width = 200;       // Thumbnail width in pixels

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
    }
    
    // Copy original image to canvas
    imagecopyresampled($canvas, $img, 0, 0, 0, 0,
                      $new_width, $new_height, $w, $h);
    
    // Save thumbnail
    imagejpeg($canvas, $destination, 85); // Quality: 85%
    imagedestroy($canvas);
    imagedestroy($img);
}

// Function to extract creation date from video metadata using FFmpeg
function get_video_creation_date($video_path) {
    $command = "ffprobe -v quiet -print_format json -show_format \"{$video_path}\"";
    $output = shell_exec($command);
    $metadata = json_decode($output, true);
    
    // Check various metadata fields that might contain creation date
    if (!empty($metadata) && isset($metadata['format']) && isset($metadata['format']['tags'])) {
        $tags = $metadata['format']['tags'];
        
        // Try different possible tag names for creation date
        $date_keys = ['creation_time', 'date', 'DateTimeOriginal', 'com.apple.quicktime.creationdate'];
        
        foreach ($date_keys as $key) {
            if (isset($tags[$key])) {
                // Try to parse the date in various formats
                $date = strtotime($tags[$key]);
                if ($date !== false) {
                    return $date;
                }
            }
        }
    }
    
    // If no valid date found in metadata, fall back to file modification time
    return filemtime($video_path);
}

// Fix issue in get_media_files function
function get_media_files($dir) {
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
        if (preg_match('/\.(jpg|jpeg|png|mp4|webm)$/i', $filename)) {
            $type = null;
            $taken = 0;
            
            // Handle different file types
            if (preg_match('/\.(jpg|jpeg|png)$/i', $filename)) {
                try {
                    $type = exif_imagetype($path);
                    
                    // Try to get EXIF data for JPEG files
                    if ($type == IMAGETYPE_JPEG) {
                        $exif = @exif_read_data($path);
                        if ($exif && isset($exif['DateTimeOriginal'])) {
                            $taken = strtotime($exif['DateTimeOriginal']);
                        }
                    }
                } catch (Exception $e) {
                    // Silently handle EXIF errors
                }
            } else if (preg_match('/\.(mp4|webm)$/i', $filename)) {
                // Set video type manually
                $type = preg_match('/\.mp4$/i', $filename) ? 'video/mp4' : 'video/webm';
                
                // Extract creation date from video metadata
                $taken = get_video_creation_date($path);
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
                'name' => $filename
            );
        }
    }
    
    return $files;
}

// Function to create video thumbnails - fixed to extract a static image
function create_video_thumbnail($source, $destination) {
    // Calculate thumbnail dimensions (maintain aspect ratio)
    global $thumb_width;
    $thumb_height = $thumb_width; // Default square, will be adjusted if needed
    
    // Extract a single frame at 1 second mark and save as JPEG
    $command = "ffmpeg -i \"{$source}\" -ss 00:00:01 -vframes 1 -s {$thumb_width}x{$thumb_height} -f image2 \"{$destination}\"";
    exec($command);
    
    // Check if thumbnail was created, if not try again with different timestamp
    if (!file_exists($destination) || filesize($destination) == 0) {
        $command = "ffmpeg -i \"{$source}\" -ss 00:00:10 -vframes 1 -s {$thumb_width}x{$thumb_height} -f image2 \"{$destination}\"";
        exec($command);
    }
    
    // If still fails, create an empty placeholder
    if (!file_exists($destination) || filesize($destination) == 0) {
        // Create a black placeholder with text
        $img = imagecreatetruecolor($thumb_width, $thumb_height);
        $text_color = imagecolorallocate($img, 255, 255, 255);
        $bg_color = imagecolorallocate($img, 0, 0, 0);
        imagefilledrectangle($img, 0, 0, $thumb_width, $thumb_height, $bg_color);
        imagestring($img, 3, $thumb_width/4, $thumb_height/2, "Video", $text_color);
        imagejpeg($img, $destination, 90);
        imagedestroy($img);
    }
}

// Main logic
$media_files = get_media_files($current_dir);

// Get sort parameter from URL
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'modified_desc';

// Get pagination parameters
$items_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 100;
$items_per_page = max(25, min(100, $items_per_page)); // Enforce limits
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

// Sort the media files based on the selected criteria
usort($media_files, function($a, $b) use ($sort) {
    switch($sort) {
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
        case 'size_desc':
            return $b['size'] - $a['size'];
        default:
            return $b['modified'] - $a['modified'];
    }
});

// Filter the media files by type if a type filter is selected
if (strpos($sort, 'type_') === 0) {
    switch ($sort) {
        case 'type_all':
            // No filtering needed
            break;
        case 'type_image':
            $media_files = array_filter($media_files, function($file) {
                return strpos($file['path'], '.jpg') !== false || 
                       strpos($file['path'], '.jpeg') !== false || 
                       strpos($file['path'], '.png') !== false;
            });
            break;
        case 'type_video':
            $media_files = array_filter($media_files, function($file) {
                return strpos($file['path'], '.mp4') !== false || 
                       strpos($file['path'], '.webm') !== false;
            });
            break;
        case 'type_jpg':
            $media_files = array_filter($media_files, function($file) {
                return strpos($file['path'], '.jpg') !== false || 
                       strpos($file['path'], '.jpeg') !== false;
            });
            break;
        case 'type_png':
            $media_files = array_filter($media_files, function($file) {
                return strpos($file['path'], '.png') !== false;
            });
            break;
        case 'type_mp4':
            $media_files = array_filter($media_files, function($file) {
                return strpos($file['path'], '.mp4') !== false;
            });
            break;
        case 'type_webm':
            $media_files = array_filter($media_files, function($file) {
                return strpos($file['path'], '.webm') !== false;
            });
            break;
    }
    
    // Use modified_desc as default sorting when filtering by type
    usort($media_files, function($a, $b) {
        return $b['modified'] - $a['modified'];
    });
}

// After sorting media files, group them by date, size or name
$grouped_files = [];

// Special handling for type filters
if (strpos($sort, 'type_') === 0 && $sort !== 'type_all') {
    // For type filters, we'll group by date
    foreach ($media_files as $file) {
        $date = $file['modified'];
        $day = date('Y-m-d', $date);
        
        if (!isset($grouped_files[$day])) {
            $grouped_files[$day] = [];
        }
        
        $grouped_files[$day][] = $file;
    }
    
    // Sort the groups by date (newest first)
    krsort($grouped_files);
} else if ($sort == 'size_desc') {
    // Define size ranges
    $size_ranges = [
        'Less than 5MB' => function($size) { return $size < 5 * 1024 * 1024; },
        '5MB to 25MB' => function($size) { return $size >= 5 * 1024 * 1024 && $size < 25 * 1024 * 1024; },
        '25MB to 50MB' => function($size) { return $size >= 25 * 1024 * 1024 && $size < 50 * 1024 * 1024; },
        '50MB to 250MB' => function($size) { return $size >= 50 * 1024 * 1024 && $size < 250 * 1024 * 1024; },
        '250MB to 500MB' => function($size) { return $size >= 250 * 1024 * 1024 && $size < 500 * 1024 * 1024; },
        '500MB to 1TB' => function($size) { return $size >= 500 * 1024 * 1024 && $size < 1024 * 1024 * 1024 * 1024; },
    ];
    
    // Group files by size range
    foreach ($media_files as $file) {
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
} else if ($sort == 'name_asc' || $sort == 'name_desc') {
    // Group files by first letter of filename
    foreach ($media_files as $file) {
        $first_letter = strtoupper(substr($file['name'], 0, 1));
        if (!ctype_alpha($first_letter)) {
            $first_letter = '#'; // Group non-alphabetic starts together
        }
        
        if (!isset($grouped_files[$first_letter])) {
            $grouped_files[$first_letter] = [];
        }
        
        $grouped_files[$first_letter][] = $file;
    }
    
    // Sort the groups alphabetically
    ksort($grouped_files);
} else {
    // Group files by date (either modified or taken date depending on sort)
    $date_field = (strpos($sort, 'taken') === 0) ? 'taken' : 'modified';
    
    foreach ($media_files as $file) {
        $date = $file[$date_field];
        $day = date('Y-m-d', $date);
        
        if (!isset($grouped_files[$day])) {
            $grouped_files[$day] = [];
        }
        
        $grouped_files[$day][] = $file;
    }
    
    // For date sorting, make sure groups are in correct order
    if ($sort == 'modified_asc' || $sort == 'taken_asc') {
        ksort($grouped_files);
    } else {
        krsort($grouped_files);
    }
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

// Paginate the files
$start_index = ($current_page - 1) * $items_per_page;
$paginated_files = array_slice($all_files, $start_index, $items_per_page);

// Rebuild grouped files with paginated data
$grouped_files = [];
if ($sort == 'size_desc') {
    // Define size ranges for paginated files
    $size_ranges = [
        'Less than 5MB' => function($size) { return $size < 5 * 1024 * 1024; },
        '5MB to 25MB' => function($size) { return $size >= 5 * 1024 * 1024 && $size < 25 * 1024 * 1024; },
        '25MB to 50MB' => function($size) { return $size >= 25 * 1024 * 1024 && $size < 50 * 1024 * 1024; },
        '50MB to 250MB' => function($size) { return $size >= 50 * 1024 * 1024 && $size < 250 * 1024 * 1024; },
        '250MB to 500MB' => function($size) { return $size >= 250 * 1024 * 1024 && $size < 500 * 1024 * 1024; },
        '500MB to 1TB' => function($size) { return $size >= 500 * 1024 * 1024 && $size < 1024 * 1024 * 1024 * 1024; },
    ];
    
    foreach ($paginated_files as $file) {
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
} else if ($sort == 'name_asc' || $sort == 'name_desc') {
    foreach ($paginated_files as $file) {
        $first_letter = strtoupper(substr($file['name'], 0, 1));
        if (!ctype_alpha($first_letter)) {
            $first_letter = '#';
        }
        
        if (!isset($grouped_files[$first_letter])) {
            $grouped_files[$first_letter] = [];
        }
        
        $grouped_files[$first_letter][] = $file;
    }
    ksort($grouped_files);
} else {
    // Group paginated files by date
    $date_field = (strpos($sort, 'taken') === 0) ? 'taken' : 'modified';
    if (strpos($sort, 'type_') === 0) {
        $date_field = 'modified';
    }
    
    foreach ($paginated_files as $file) {
        $date = $file[$date_field];
        $day = date('Y-m-d', $date);
        
        if (!isset($grouped_files[$day])) {
            $grouped_files[$day] = [];
        }
        
        $grouped_files[$day][] = $file;
    }
    
    if ($sort == 'modified_asc' || $sort == 'taken_asc') {
        ksort($grouped_files);
    } else {
        krsort($grouped_files);
    }
}

// Generate thumbnails for each media file
foreach ($paginated_files as $file) {
    // Create thumbnail path
    $thumb_path = $thumbs_dir . '/' . basename($file['path']);
    
    // Skip if thumbnail already exists
    if (file_exists($thumb_path)) continue;
    
    // Handle different file types
    switch ($file['type']) {
        case IMAGETYPE_JPEG:
        case IMAGETYPE_PNG:
            create_thumbnail($file['path'], $thumb_path, $thumb_width);
            break;
        case 'video/mp4':
        case 'video/webm':
            create_video_thumbnail($file['path'], $thumb_path);
            break;
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
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 250px;
            min-width: 250px;
            max-width: 250px;
            background-color: #2c3e50;
            padding: 20px;
            overflow-y: auto;
            position: sticky;
            top: 0;
            height: 100vh;
            color: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        
        .sidebar-header {
            margin-bottom: 20px;
            text-align: center;
        }
        
        .controls {
            margin-bottom: 1.5rem;
        }
        
        .controls select {
            width: 100%;
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid #ddd;
            background: #34495e;
            color: white;
        }
        
        .controls .group {
            margin-bottom: 10px;
        }
        
        .controls .group-label {
            font-size: 0.9em;
            opacity: 0.8;
            margin-bottom: 5px;
            display: block;
        }
        
        .group-controls {
            display: flex;
            margin-bottom: 10px;
            gap: 10px;
        }
        
        .group-controls button {
            flex: 1;
            background: #34495e;
            color: white;
            border: none;
            padding: 8px 0;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9em;
            transition: background 0.2s;
        }
        
        .group-controls button:hover {
            background: #3498db;
        }
        
        .group-nav {
            margin-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            padding-top: 15px;
        }
        
        .nav-section {
            margin-bottom: 20px;
        }
        
        .nav-section-title {
            font-weight: bold;
            margin-bottom: 10px;
            text-transform: uppercase;
            font-size: 0.8em;
            letter-spacing: 1px;
            color: rgba(255, 255, 255, 0.6);
        }
        
        .folder-nav {
            margin-bottom: 20px;
        }
        
        .folder-nav-item {
            padding: 10px 15px;
            border-radius: 4px;
            margin-bottom: 5px;
            cursor: pointer;
            transition: background 0.2s;
            display: flex;
            align-items: center;
        }
        
        .folder-nav-item:hover {
            background: #34495e;
        }
        
        .folder-nav-item.active {
            background: #3498db;
        }
        
        .folder-icon {
            margin-right: 10px;
            font-size: 1.2em;
        }
        
        .group-nav-item {
            padding: 10px 15px;
            border-radius: 4px;
            margin-bottom: 5px;
            cursor: pointer;
            transition: background 0.2s;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .group-nav-item:hover {
            background: #34495e;
        }
        
        .group-nav-item.active {
            background: #3498db;
        }
        
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
        
        .content {
            flex-grow: 1;
            padding: 20px;
            overflow-y: auto;
        }
        
        .date-section {
            margin-bottom: 40px;
        }
        
        .date-header {
            margin-bottom: 15px;
            padding: 10px;
            background: #3498db;
            color: white;
            border-radius: 4px;
            font-weight: bold;
        }
        
        .date-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            grid-gap: 15px;
            width: 100%;
        }
        
        @media (min-width: 1200px) {
            .date-gallery {
                grid-template-columns: repeat(4, 1fr);
            }
        }
        
        @media (max-width: 1199px) and (min-width: 900px) {
            .date-gallery {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 899px) and (min-width: 600px) {
            .date-gallery {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 599px) {
            .date-gallery {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 700px) {
            body {
                flex-direction: column;
            }
            
            .sidebar {
                width: auto;
                height: auto;
                min-width: auto;
                max-width: none;
                position: relative;
            }
        }
        
        .item {
            break-inside: avoid;
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            background: white;
            cursor: pointer;
            transition: transform 0.2s ease;
            aspect-ratio: 1;
        }
        
        .item:hover {
            transform: translateY(-5px);
        }
        
        .item img, .item video {
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
        
        .item:hover .info {
            opacity: 1;
        }
        
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
        
        .group-hidden {
            display: none;
        }
        
        /* Lightbox styles */
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
        
        .lightbox.active {
            display: flex;
        }
        
        .lightbox-content {
            max-width: 90%;
            max-height: 90%;
            position: relative;
        }
        
        .lightbox-content img, .lightbox-content video {
            max-width: 100%;
            max-height: 85vh;
            display: block;
            border-radius: 4px;
            object-fit: contain;
        }
        
        .close-lightbox {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 30px;
            color: #fff;
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
        
        /* Empty state styling */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #7f8c8d;
        }
        
        .empty-state h3 {
            margin-bottom: 15px;
            font-size: 24px;
        }
        
        .empty-state p {
            margin-bottom: 25px;
            font-size: 16px;
        }

        .lightbox-prev, .lightbox-next {
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
            transition: background 0.2s, transform 0.2s;
            font-weight: bold;
            z-index: 1002;
        }

        .lightbox-prev {
            left: 40px;
        }

        .lightbox-next {
            right: 40px;
        }

        .lightbox-prev:hover, .lightbox-next:hover {
            background: rgba(0,0,0,0.9);
            transform: translateY(-50%) scale(1.1);
        }

        @media (max-width: 768px) {
            .lightbox-prev, .lightbox-next {
                width: 60px;
                height: 60px;
                font-size: 30px;
            }
            
            .lightbox-prev {
                left: 20px;
            }
            
            .lightbox-next {
                right: 20px;
            }
        }

        /* Pagination styles */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 20px 0;
            gap: 10px;
            flex-wrap: wrap;
        }

        .pagination-info {
            background: #3498db;
            color: white;
            padding: 8px 15px;
            border-radius: 4px;
            font-size: 0.9em;
            margin: 0 10px;
        }

        .pagination a, .pagination .current {
            padding: 8px 12px;
            border: 1px solid #ddd;
            text-decoration: none;
            color: #333;
            border-radius: 4px;
            transition: background 0.2s;
        }

        .pagination a:hover {
            background: #f5f5f5;
        }

        .pagination .current {
            background: #3498db;
            color: white;
            border-color: #3498db;
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

        .items-per-page select {
            padding: 6px 10px;
            border-radius: 4px;
            border: 1px solid #ddd;
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
            <a href="?dir=<?= urlencode($path) ?>&sort=<?= $sort ?>" style="text-decoration: none; color: inherit;">
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
                <span class="group-label">By Last Modified Date</span>
                <select id="sortByModified" onchange="location.href='?dir=<?= urlencode($current_dir) ?>&sort='+this.value+'&per_page=<?= $items_per_page ?>&page=1'">
                    <option value="modified_desc" <?= $sort == 'modified_desc' ? 'selected' : '' ?>>Last Modified - Newest First</option>
                    <option value="modified_asc" <?= $sort == 'modified_asc' ? 'selected' : '' ?>>Last Modified - Oldest First</option>
                </select>
            </div>
            
            <div class="group">
                <span class="group-label">By Date Taken</span>
                <select id="sortByTaken" onchange="location.href='?dir=<?= urlencode($current_dir) ?>&sort='+this.value+'&per_page=<?= $items_per_page ?>&page=1'">
                    <option value="taken_desc" <?= $sort == 'taken_desc' ? 'selected' : '' ?>>Date Taken - Newest First</option>
                    <option value="taken_asc" <?= $sort == 'taken_asc' ? 'selected' : '' ?>>Date Taken - Oldest First</option>
                </select>
            </div>
            
            <div class="group">
                <span class="group-label">Other Sorting</span>
                <select id="sortByOther" onchange="location.href='?dir=<?= urlencode($current_dir) ?>&sort='+this.value+'&per_page=<?= $items_per_page ?>&page=1'">
                    <option value="size_desc" <?= $sort == 'size_desc' ? 'selected' : '' ?>>By Size</option>
                    <option value="name_asc" <?= $sort == 'name_asc' ? 'selected' : '' ?>>By Name (A-Z)</option>
                    <option value="name_desc" <?= $sort == 'name_desc' ? 'selected' : '' ?>>By Name (Z-A)</option>
                    <option disabled>───── By File Type ─────</option>
                    <option value="type_all" <?= $sort == 'type_all' ? 'selected' : '' ?>>All Files</option>
                    <option value="type_image" <?= $sort == 'type_image' ? 'selected' : '' ?>>Images Only (jpg, png)</option>
                    <option value="type_video" <?= $sort == 'type_video' ? 'selected' : '' ?>>Videos Only (mp4, webm)</option>
                    <option value="type_jpg" <?= $sort == 'type_jpg' ? 'selected' : '' ?>>JPEG Only</option>
                    <option value="type_png" <?= $sort == 'type_png' ? 'selected' : '' ?>>PNG Only</option>
                    <option value="type_mp4" <?= $sort == 'type_mp4' ? 'selected' : '' ?>>MP4 Videos Only</option>
                    <option value="type_webm" <?= $sort == 'type_webm' ? 'selected' : '' ?>>WebM Videos Only</option>
                </select>
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
                    <?php if ($sort == 'size_desc'): ?>
                        <?= $group_name ?>
                    <?php elseif ($sort == 'name_asc' || $sort == 'name_desc'): ?>
                        <?= $group_name ?>
                    <?php elseif (strpos($sort, 'type_') === 0 && $sort !== 'type_all'): ?>
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
                    <a href="?dir=<?= urlencode($current_dir) ?>&sort=<?= $sort ?>&per_page=<?= $items_per_page ?>&page=1">&laquo; First</a>
                    <a href="?dir=<?= urlencode($current_dir) ?>&sort=<?= $sort ?>&per_page=<?= $items_per_page ?>&page=<?= $current_page - 1 ?>">&lsaquo; Prev</a>
                <?php else: ?>
                    <span class="disabled">&laquo; First</span>
                    <span class="disabled">&lsaquo; Prev</span>
                <?php endif; ?>
                
                <div class="pagination-info">
                    Page <?= $current_page ?> of <?= $total_pages ?> (<?= $total_files ?> items)
                </div>
                
                <div class="items-per-page">
                    <label>Items per page:</label>
                    <select onchange="location.href='?dir=<?= urlencode($current_dir) ?>&sort=<?= $sort ?>&per_page='+this.value+'&page=1'">
                        <option value="25" <?= $items_per_page == 25 ? 'selected' : '' ?>>25</option>
                        <option value="50" <?= $items_per_page == 50 ? 'selected' : '' ?>>50</option>
                        <option value="75" <?= $items_per_page == 75 ? 'selected' : '' ?>>75</option>
                        <option value="100" <?= $items_per_page == 100 ? 'selected' : '' ?>>100</option>
                    </select>
                </div>
                
                <?php if ($current_page < $total_pages): ?>
                    <a href="?dir=<?= urlencode($current_dir) ?>&sort=<?= $sort ?>&per_page=<?= $items_per_page ?>&page=<?= $current_page + 1 ?>">Next &rsaquo;</a>
                    <a href="?dir=<?= urlencode($current_dir) ?>&sort=<?= $sort ?>&per_page=<?= $items_per_page ?>&page=<?= $total_pages ?>">Last &raquo;</a>
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
                        <?php if ($sort == 'size_desc'): ?>
                            <?= $group_name ?>
                        <?php elseif ($sort == 'name_asc' || $sort == 'name_desc'): ?>
                            <?= $group_name == '#' ? 'Other' : "Files starting with '$group_name'" ?>
                        <?php elseif (strpos($sort, 'type_') === 0 && $sort !== 'type_all'): ?>
                            <?php 
                            $type_label = '';
                            switch ($sort) {
                                case 'type_image': $type_label = 'Images'; break;
                                case 'type_video': $type_label = 'Videos'; break;
                                case 'type_jpg': $type_label = 'JPEG Images'; break;
                                case 'type_png': $type_label = 'PNG Images'; break;
                                case 'type_mp4': $type_label = 'MP4 Videos'; break;
                                case 'type_webm': $type_label = 'WebM Videos'; break;
                            }
                            echo $type_label . ' - ' . date('l, F j, Y', strtotime($group_name));
                            ?>
                        <?php else: ?>
                            <?= date('l, F j, Y', strtotime($group_name)) ?>
                        <?php endif; ?>
                    </div>
                    <div class="date-gallery">
                        <?php foreach ($files as $file): ?>
                        <div class="item" data-index="<?= $global_index++ ?>">
                            <?php if (strpos($file['type'], 'video/') === 0): ?>
                                <video poster="<?= $thumbs_dir . '/' . basename($file['path']) ?>">
                                    <source src="<?= $file['path'] ?>" type="<?= $file['type'] ?>">
                                </video>
                                <div class="play-button">▶</div>
                            <?php else: ?>
                                <img src="<?= $thumbs_dir . '/' . basename($file['path']) ?>" 
                                    alt="<?= basename($file['path']) ?>"
                                    loading="lazy">
                            <?php endif; ?>
                            <div class="info">
                                <div class="info-filename"><?= basename($file['path']) ?></div>
                                Size: <?= round($file['size'] / 1024 / 1024, 1) ?> MB
                                <?php if ($file['taken'] > 0): ?>
                                    <br>Taken: <?= date('Y-m-d H:i', $file['taken']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <!-- Bottom pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($current_page > 1): ?>
                    <a href="?dir=<?= urlencode($current_dir) ?>&sort=<?= $sort ?>&per_page=<?= $items_per_page ?>&page=1">&laquo; First</a>
                    <a href="?dir=<?= urlencode($current_dir) ?>&sort=<?= $sort ?>&per_page=<?= $items_per_page ?>&page=<?= $current_page - 1 ?>">&lsaquo; Prev</a>
                <?php else: ?>
                    <span class="disabled">&laquo; First</span>
                    <span class="disabled">&lsaquo; Prev</span>
                <?php endif; ?>
                
                <div class="pagination-info">
                    Page <?= $current_page ?> of <?= $total_pages ?> (<?= $total_files ?> items)
                </div>
                
                <?php if ($current_page < $total_pages): ?>
                    <a href="?dir=<?= urlencode($current_dir) ?>&sort=<?= $sort ?>&per_page=<?= $items_per_page ?>&page=<?= $current_page + 1 ?>">Next &rsaquo;</a>
                    <a href="?dir=<?= urlencode($current_dir) ?>&sort=<?= $sort ?>&per_page=<?= $items_per_page ?>&page=<?= $total_pages ?>">Last &raquo;</a>
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Create a fixed array of paginated media files for the lightbox
            const allMediaFiles = [];
            <?php 
            $index = 0;
            foreach ($grouped_files as $group_name => $files): 
                foreach ($files as $file): 
            ?>
                allMediaFiles[<?= $index++ ?>] = <?= json_encode($file) ?>;
            <?php 
                endforeach; 
            endforeach; 
            ?>
            
            // Set the selected option based on the current URL parameter
            const urlParams = new URLSearchParams(window.location.search);
            const sortBy = urlParams.get('sort');
            if (sortBy) {
                if (sortBy.startsWith('modified_')) {
                    document.getElementById('sortByModified').value = sortBy;
                } else if (sortBy.startsWith('taken_')) {
                    document.getElementById('sortByTaken').value = sortBy;
                } else {
                    document.getElementById('sortByOther').value = sortBy;
                }
            }
            
            // Initialize lightbox
            const lightbox = document.getElementById('lightbox');
            const lightboxContent = document.getElementById('lightbox-content');
            const closeButton = document.querySelector('.close-lightbox');
            const prevButton = document.querySelector('.lightbox-prev');
            const nextButton = document.querySelector('.lightbox-next');
            const items = document.querySelectorAll('.item');
            
            let currentIndex = 0;
            
            // Function to show media at specific index
            function showMedia(index) {
                // Make sure index is within bounds
                if (index < 0) index = allMediaFiles.length - 1;
                if (index >= allMediaFiles.length) index = 0;
                
                // Update current index
                currentIndex = index;
                
                // Clear previous content
                lightboxContent.innerHTML = '';
                
                // Get the file to display
                const file = allMediaFiles[index];
                
                if (file.type && file.type.toString().includes('video')) {
                    // Create video element for lightbox
                    const video = document.createElement('video');
                    video.controls = true;
                    video.autoplay = true;
                    
                    const source = document.createElement('source');
                    source.src = file.path;
                    source.type = file.type;
                    
                    video.appendChild(source);
                    lightboxContent.appendChild(video);
                } else {
                    // Create image element for lightbox
                    const img = document.createElement('img');
                    img.src = file.path;
                    img.alt = file.path.split('/').pop();
                    lightboxContent.appendChild(img);
                }
            }
            
            // Open lightbox when clicking an item
            items.forEach(item => {
                item.addEventListener('click', function() {
                    currentIndex = parseInt(this.getAttribute('data-index'));
                    showMedia(currentIndex);
                    lightbox.classList.add('active');
                });
            });
            
            // Navigate to previous media
            prevButton.addEventListener('click', function() {
                showMedia(currentIndex - 1);
            });
            
            // Navigate to next media
            nextButton.addEventListener('click', function() {
                showMedia(currentIndex + 1);
            });
            
            // Close lightbox when clicking the close button
            closeButton.addEventListener('click', function() {
                lightbox.classList.remove('active');
                
                // Pause any videos when closing lightbox
                const video = lightboxContent.querySelector('video');
                if (video) {
                    video.pause();
                }
            });
            
            // Close lightbox when clicking outside the content
            lightbox.addEventListener('click', function(e) {
                if (e.target === lightbox) {
                    lightbox.classList.remove('active');
                    
                    // Pause any videos when closing lightbox
                    const video = lightboxContent.querySelector('video');
                    if (video) {
                        video.pause();
                    }
                }
            });
            
            // Touch/swipe support for mobile devices
            let touchStartX = 0;
            let touchStartY = 0;
            let touchEndX = 0;
            let touchEndY = 0;
            
            lightboxContent.addEventListener('touchstart', function(e) {
                touchStartX = e.changedTouches[0].screenX;
                touchStartY = e.changedTouches[0].screenY;
            });
            
            lightboxContent.addEventListener('touchend', function(e) {
                touchEndX = e.changedTouches[0].screenX;
                touchEndY = e.changedTouches[0].screenY;
                
                const deltaX = touchEndX - touchStartX;
                const deltaY = touchEndY - touchStartY;
                
                // Check if it's a horizontal swipe (more horizontal than vertical movement)
                if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > 50) {
                    if (deltaX > 0) {
                        // Swipe right - previous image
                        showMedia(currentIndex - 1);
                    } else {
                        // Swipe left - next image
                        showMedia(currentIndex + 1);
                    }
                    e.preventDefault(); // Prevent default touch behavior
                } else if (Math.abs(deltaX) < 30 && Math.abs(deltaY) < 30) {
                    // Small movement, treat as tap to close
                    lightbox.classList.remove('active');
                    
                    // Pause any videos when closing lightbox
                    const video = lightboxContent.querySelector('video');
                    if (video) {
                        video.pause();
                    }
                }
            });
            
            // Prevent default touch behavior on lightbox content to enable swipe
            lightboxContent.addEventListener('touchmove', function(e) {
                e.preventDefault();
            });
            
            // Handle keyboard navigation
            document.addEventListener('keydown', function(e) {
                if (lightbox.classList.contains('active')) {
                    switch (e.key) {
                        case 'Escape':
                            // Close lightbox
                            lightbox.classList.remove('active');
                            
                            // Pause any videos when closing lightbox
                            const video = lightboxContent.querySelector('video');
                            if (video) {
                                video.pause();
                            }
                            break;
                            
                        case 'ArrowLeft':
                            // Previous media
                            showMedia(currentIndex - 1);
                            break;
                            
                        case 'ArrowRight':
                            // Next media
                            showMedia(currentIndex + 1);
                            break;
                    }
                }
            });
            
            // Handle group navigation (remaining code as before)
            const groupNavItems = document.querySelectorAll('.group-nav-item');
            groupNavItems.forEach(item => {
                item.addEventListener('click', function() {
                    const groupId = this.getAttribute('data-group');
                    const groupItems = document.getElementById('group-' + groupId);
                    
                    // Toggle visibility
                    if (this.classList.contains('active')) {
                        this.classList.remove('active');
                        groupItems.classList.add('group-hidden');
                    } else {
                        this.classList.add('active');
                        groupItems.classList.remove('group-hidden');
                    }
                    
                    // Scroll to the group if it's being shown
                    if (!groupItems.classList.contains('group-hidden')) {
                        groupItems.scrollIntoView({ behavior: 'smooth' });
                    }
                });
            });
            
            // Handle expand/collapse all functionality
            const expandAllBtn = document.getElementById('expand-all');
            const collapseAllBtn = document.getElementById('collapse-all');
            
            if (expandAllBtn && collapseAllBtn) {
                expandAllBtn.addEventListener('click', function() {
                    groupNavItems.forEach(item => {
                        item.classList.add('active');
                        const groupId = item.getAttribute('data-group');
                        const groupItems = document.getElementById('group-' + groupId);
                        groupItems.classList.remove('group-hidden');
                    });
                });
                
                collapseAllBtn.addEventListener('click', function() {
                    groupNavItems.forEach(item => {
                        item.classList.remove('active');
                        const groupId = item.getAttribute('data-group');
                        const groupItems = document.getElementById('group-' + groupId);
                        groupItems.classList.add('group-hidden');
                    });
                });
            }
        });
    </script>
</body>
</html>