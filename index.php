<?php
// Configuration
$base_dir = './content';  // Base directory containing media files
$thumbs_dir = './thumbs';  // Directory for generated thumbnails
$thumb_width = 200;       // Thumbnail width in pixels

// Create thumbnail directory if it doesn't exist
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

// Fix issue in get_media_files function
function get_media_files($dir) {
    $files = array();
    
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
            }
            
            $files[] = array(
                'path' => $path,
                'type' => $type,
                'modified' => $file_info->getMTime(),
                'taken' => $taken,
                'size' => $file_info->getSize()
            );
        }
    }
    
    return $files;
}

// Function to create video thumbnails
function create_video_thumbnail($source, $destination) {
    // Generate thumbnail using ffmpeg (assumes ffmpeg is available)
    exec("ffmpeg -i {$source} -ss 00:00:10 -vframes 1 {$destination}");
}

// Main logic
$media_files = get_media_files($base_dir);

// Add this code before the HTML output to handle sorting

// Get sort parameter from URL
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'modified_desc';

// Sort the media files based on the selected criteria
usort($media_files, function($a, $b) use ($sort) {
    switch($sort) {
        case 'modified_asc':
            return $a['modified'] - $b['modified'];
        case 'taken_desc':
            // If taken date is available use it, otherwise fall back to modified date
            $a_date = $a['taken'] > 0 ? $a['taken'] : $a['modified'];
            $b_date = $b['taken'] > 0 ? $b['taken'] : $b['modified'];
            return $b_date - $a_date;
        case 'size_desc':
            return $b['size'] - $a['size'];
        case 'modified_desc':
        default:
            return $b['modified'] - $a['modified'];
    }
});

// Generate thumbnails for each media file
foreach ($media_files as $file) {
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

// Simple HTML viewer
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Media Gallery</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        
        .controls {
            margin-bottom: 1rem;
            display: flex;
            gap: 0.5rem;
            justify-content: center;
        }
        
        .controls select {
            padding: 8px 16px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        
        .gallery {
            column-count: 4;
            column-gap: 15px;
            width: 100%;
        }
        
        @media (max-width: 1200px) {
            .gallery {
                column-count: 3;
            }
        }
        
        @media (max-width: 800px) {
            .gallery {
                column-count: 2;
            }
        }
        
        @media (max-width: 500px) {
            .gallery {
                column-count: 1;
            }
        }
        
        .item {
            break-inside: avoid;
            margin-bottom: 15px;
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            background: white;
            cursor: pointer;
            transition: transform 0.2s ease;
        }
        
        .item:hover {
            transform: translateY(-5px);
        }
        
        .item img, .item video {
            width: 100%;
            height: auto;
            display: block;
            border-radius: 8px;
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
    </style>
</head>
<body>
    <div class="controls">
        <select id="sortBy" onchange="location.href='?sort='+this.value">
            <option value="modified_desc" <?= $sort == 'modified_desc' ? 'selected' : '' ?>>Newest First</option>
            <option value="modified_asc" <?= $sort == 'modified_asc' ? 'selected' : '' ?>>Oldest First</option>
            <option value="taken_desc" <?= $sort == 'taken_desc' ? 'selected' : '' ?>>By Capture Date</option>
            <option value="size_desc" <?= $sort == 'size_desc' ? 'selected' : '' ?>>Largest Files</option>
        </select>
    </div>

    <div class="gallery" id="gallery">
        <?php foreach ($media_files as $index => $file): ?>
        <div class="item" data-index="<?= $index ?>">
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
                <?= basename($file['path']) ?><br>
                Size: <?= round($file['size'] / 1024 / 1024, 1) ?> MB
                <?php if ($file['taken'] > 0): ?>
                    <br>Taken: <?= date('Y-m-d H:i', $file['taken']) ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Lightbox container -->
    <div class="lightbox" id="lightbox">
        <span class="close-lightbox">&times;</span>
        <div class="lightbox-content" id="lightbox-content"></div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Set the selected option based on the current URL parameter
            const sortBy = new URLSearchParams(window.location.search).get('sort');
            if (sortBy) {
                document.getElementById('sortBy').value = sortBy;
            }
            
            // Initialize lightbox
            const lightbox = document.getElementById('lightbox');
            const lightboxContent = document.getElementById('lightbox-content');
            const closeButton = document.querySelector('.close-lightbox');
            const items = document.querySelectorAll('.item');
            const mediaFiles = <?= json_encode($media_files) ?>;
            
            // Open lightbox when clicking an item
            items.forEach(item => {
                item.addEventListener('click', function() {
                    const index = parseInt(this.getAttribute('data-index'));
                    const file = mediaFiles[index];
                    
                    lightboxContent.innerHTML = '';
                    
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
                    
                    lightbox.classList.add('active');
                });
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
            
            // Handle keyboard navigation (Esc to close)
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && lightbox.classList.contains('active')) {
                    lightbox.classList.remove('active');
                    
                    // Pause any videos when closing lightbox
                    const video = lightboxContent.querySelector('video');
                    if (video) {
                        video.pause();
                    }
                }
            });
        });
    </script>
</body>
</html>