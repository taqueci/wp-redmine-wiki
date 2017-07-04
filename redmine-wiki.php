<?php
/*
Plugin Name: Redmine Wiki
Plugin URI: https://github.com/taqueci/wp-redmine-wiki/
Description: Embeds Redmine Wiki page.
Version: 0.2.0
Author: Takeshi Nakamura
Author URI: https://github.com/taqueci/
License: GPL2
*/

class RedmineWiki {
	const DATA_DIR = 'wp-content/uploads/rwiki';
	const DEFAULT_ID = 'rwiki';

	function __construct($id, $url, $proj, $wiki, $key) {
		$u = parse_url($url);

		$this->id      = $id;
		$this->url     = $url;
		$this->path    = isset($u['path']) ? $u['path'] : '';
		$this->project = $proj;
		$this->wiki    = $wiki;
		$this->key     = $key;
	}

	public function content($page, $param) {
		$index = $this->page_index();

		if (!in_array($page, $index)) $page = $this->wiki;

		$html = $this->page_html($page, $index);

		return  $this->html($html, $index, $param);
	}

	private function page_index() {
		$url  = $this->url;
		$proj = $this->project;
		$key  = $this->key;

		$u = "$url/projects/$proj/wiki/index.json?key=$key";

		$json = $this->get_file($u);

		if ($json === false) return array();

		$var = json_decode($json);

		$index = array();

		foreach ($var->wiki_pages as $p) {
			$index[] = $p->title;
		}

		return $index;
	}

	private function page_html($page, $index) {
		$url  = $this->url;
		$proj = $this->project;
		$key  = $this->key;

		$u = "$url/projects/$proj/wiki/$page.html?key=$key";

		return $this->get_file($u);
	}

	private function html($html, $index, $param) {
		$url  = $this->url;
		$path = $this->path;
		$proj = $this->project;
		$key  = $this->key;
		$id   = $this->id;

		$base_url = preg_replace("@$path$@", '', $url);

		$datadir_path = ABSPATH . self::DATA_DIR;
		$datadir_url = home_url('/') . self::DATA_DIR;

		$dom = new DOMDocument;

		@$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));

		$tag_a = $dom->getElementsByTagName('a');

		foreach ($tag_a as $x) {
			$p = $x->getAttribute('href');

			if (preg_match("@^$path/attachments/@", $p)) {
				if (preg_match("@^$path/attachments/(\d+)@", $p, $m)) {
					// For thumbnail macro
					$u = "$url/attachments/download/" . $m[1];
				} else {
					$u = $base_url . $p;
				}

				$h = md5($u);
				$s = substr($h, 0, 2);
				$base = basename($p);
				$b = urldecode($base);

				$this->copy_file("$u?key=$key", "$datadir_path/$s/$h/$b");

				$x->setAttribute('href', "$datadir_url/$s/$h/$base");
			}
			else {
				foreach ($index as $i) {
					// Convert wiki page link.
					if ($p == "$i.html") {
						$q = $param;
						$q[$id] = $i;

						$x->setAttribute('href', '?' . http_build_query($q));
						break;
					}
				}
			}
		}

		$tag_img = $dom->getElementsByTagName('img');

		foreach ($tag_img as $x) {
			$p = $x->getAttribute('src');

			$u = $base_url . $p;

			$h = md5($u);
			$s = substr($h, 0, 2);
			$base = basename($p);
			$b = urldecode($base);

			$this->copy_file("$u?key=$key", "$datadir_path/$s/$h/$b");

			$x->setAttribute('src', "$datadir_url/$s/$h/$base");
		}

		$body = $dom->getElementsByTagName('body')->item(0);

		$html = array();

		foreach ($body->childNodes as $n) {
			$html[] = $dom->saveHTML($n);
		}

		return join('', $html);
	}

	private function get_file($path) {
		$context = stream_context_create(array(
				'ssl' => array(
					'verify_peer' => false,
					'verify_peer_name' => false,
				)
			));

		return file_get_contents($path, false, $context);
	}

	private function copy_file($from, $to) {
		if (file_exists($to)) return;

		$file = $this->get_file($from);

		wp_mkdir_p(dirname($to));

		file_put_contents($to, $file);
	}
}

function redmine_wiki($atts) {
	extract(shortcode_atts(array(
				'url'  => 'http://localhost/redmine',
				'project' => 'foo', // Project identifier
				'wiki' => 'Wiki', // Wiki start page
				'key'  => '243f6a8885a308d313198a2e03707344a4093822',
				'id' => RedmineWiki::DEFAULT_ID
			), $atts));

	$page = $_GET[$id];

	$rwiki = new RedmineWiki($id, $url, $project, $wiki, $key);

	return $rwiki->content($page, $_GET);
}

add_shortcode('rwiki', 'redmine_wiki');
