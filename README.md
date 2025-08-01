# PHP Instant Gallery

### About:
This project was created because I found it very frustrating that I couldn't find a useful open source PHP gallery that would just work and didn't require a lot of fiddling to show pictures and videos in a directory.
The idea here is that all you need to do is point this at a directory and it will generate the thumbnails and then show you the pictures.  If you have a directory with hundreds or thousands of pictures, it will take a bit for the script to generate all the thumbnails.

It's still a work in progress, and I'll add more documentation once I have productionalized the thing.

# How to use:
1. Copy index.php to directory above where gallery of media is.
2. Change $base_dir to media directory
3. Change $thumbs_dir to directory where thumbnails will be stored
4. (Optional) Add `'Display Name' => 'path/to/directory',` for each wanted sub-directory to navigate to [still experimental, be warned]
5. (Optional) Add `'Google' => 'https://google.com',` for each external link you want to add to the page.

Done!

# Requirements:
* [PHP 5.2=>](https://www.php.net/)
* [ffmpeg](https://ffmpeg.org/) (in path)
* [ffprobe](https://ffmpeg.org/ffprobe.html) (in path)
