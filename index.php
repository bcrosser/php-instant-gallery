<?php
// Function to create thumbnail images
function createThumbnail($src, $dest, $targetWidth = 200) {
    $info = getimagesize($src);
    $mime = $info['mime'];

    switch ($mime) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($src);
            break;
        case 'image/png':
            $image = imagecreatefrompng($src);
            break;
        case 'image/gif':
            $image = imagecreatefromgif($src);
            break;
        default:
            throw new Exception('Unsupported image format.');
    }

    $width = imagesx($image);
    $height = imagesy($image);
    $targetHeight = ($height / $width) * $targetWidth;

    $thumbnail = imagecreatetruecolor($targetWidth, $targetHeight);
    imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

    switch ($mime) {
        case 'image/jpeg':
            imagejpeg($thumbnail, $dest);
            break;
        case 'image/png':
            imagepng($thumbnail, $dest);
            break;
        case 'image/gif':
            imagegif($thumbnail, $dest);
            break;
    }

    imagedestroy($image);
    imagedestroy($thumbnail);
}

function createVideoThumbnail($videoPath, $thumbnailPath, $thumbnailWidth = 320, $thumbnailHeight = 200) {
    if (!commandExists('ffmpeg')) {
        throw new Exception('FFmpeg is not installed. Please install FFmpeg to generate thumbnails.');
    }
    $command = "ffmpeg -i {$videoPath} -ss 00:00:01 -vframes 1 -s {$thumbnailWidth}x{$thumbnailHeight} {$thumbnailPath}";
    exec($command, $output, $returnCode);
    return $returnCode === 0;
}

function commandExists($command) {
    $output = shell_exec("where {$command}");
    return !empty($output);
}

function groupFilesByDate($files) {
    $groupedFiles = [];
    foreach ($files as $file) {
        $date = date('Y-m-d', filemtime($file));
        $groupedFiles[$date][] = $file;
    }
    krsort($groupedFiles);
    return $groupedFiles;
}

$pics_dir = 'pics/.stversions';
$vids_dir = 'concert-vids/.stversions';
$thumbDir = 'thumbs';
$thumbDir2 = 'vid_thumbs';

if (!file_exists($thumbDir)) mkdir($thumbDir, 0777, true);
if (!file_exists($thumbDir2)) mkdir($thumbDir2, 0777, true);

$images = glob("$pics_dir/*.{jpg,jpeg,png,gif}", GLOB_BRACE);
foreach ($images as $image) {
    $imageName = basename($image);
    $thumbPath = "$thumbDir/$imageName";
    if (!file_exists($thumbPath)) createThumbnail($image, $thumbPath);
}

$videos = glob("$vids_dir/*.{mp4}", GLOB_BRACE);
foreach ($videos as $video) {
    $info = pathinfo($video);
    $thumbPath = "$thumbDir2/{$info['filename']}.jpg";
    if (!file_exists($thumbPath)) createVideoThumbnail($video, $thumbPath);
}

$allFiles = array_merge($images, $videos);
$groupedFiles = groupFilesByDate($allFiles);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pinterest-Style Gallery</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f0f0;
            margin: 0;
            padding: 20px;
        }
        .date-group {
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid #ccc;
        }
        		.date-header {
            margin-bottom: 10px;
            color: #333;
            cursor: pointer;
            background-color: #007bff;
            color: #fff;
            padding: 10px;
            border-radius: 5px;
            display: inline-block;
            position: relative;
			font-size: 9px;
        }
        .date-header:after {
			content: ' ▼';
            right: -20px;
        }
        .date-header.closed:after {
            right: -20px;
            content: ' ►';
        }
        .gallery {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .gallery-item {
            flex: 1 1 calc(33.333% - 10px);
            box-sizing: border-box;
            position: relative;
        }
        .gallery-item img {
            width: 100%;
            border-radius: 4px;
            display: block;
        }
        .gallery-item .play-button {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 30px;
            color: white;
            background-color: rgba(0, 0, 0, 0.6);
            border-radius: 50%;
            padding: 10px;
            cursor: pointer;
            display: none;
        }
        .gallery-item:hover .play-button {
            display: block;
        }
        .hidden-content {
            display: none;
        }
    </style>
    <script>
        function toggleVisibility(date) {
            const group = document.getElementById(`group-${date}-content`);
            const header = document.getElementById(`header-${date}`);
            group.classList.toggle('hidden-content');
            header.classList.toggle('closed');
        }

        function playVideo(thumbnail, videoSrc) {
            const parent = thumbnail.parentNode;
            const videoElement = document.createElement('video');
            videoElement.src = videoSrc;
            videoElement.controls = true;
            videoElement.style.width = '100%';
            videoElement.style.borderRadius = '4px';
            parent.innerHTML = '';
            parent.appendChild(videoElement);
        }
    </script>
</head>
<body>
    <?php
    foreach ($groupedFiles as $date => $files) {
        echo '<div class="date-group">';
        echo '<h2 class="date-header" id="header-' . $date . '" onclick="toggleVisibility(\'' . $date . '\')">' . $date . '</h2>';
        echo '<div class="gallery" id="group-' . $date . '-content">';
        foreach ($files as $file) {
            $fileType = mime_content_type($file);
            $isImage = strpos($fileType, 'image') !== false;
            $isVideo = strpos($fileType, 'video') !== false;
            echo '<div class="gallery-item">';
            if ($isImage) {
                $thumbPath = "$thumbDir/" . basename($file);
                echo '<a href="' . $file . '" download><img src="' . $thumbPath . '" alt="Image"></a>';
            } elseif ($isVideo) {
                $thumbPath = "$thumbDir2/" . pathinfo($file, PATHINFO_FILENAME) . ".jpg";
                echo '<img src="' . $thumbPath . '" alt="Video Thumbnail" onclick="playVideo(this, \'' . $file . '\')">';
                echo '<div class="play-button" onclick="playVideo(this.parentNode.querySelector(\'img\'), \'' . $file . '\')">▶</div>';
            }
            echo '</div>';
        }
        echo '</div>'; // Close gallery div
        echo '</div>'; // Close date-group div
    }
    ?>
</body>
</html>
