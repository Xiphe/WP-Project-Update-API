<?php
class Easter {
	public function init() {
		if ($_GET['action'] === 'hire' &&
			$_GET['slug'] === 'hannes' &&
			$_GET['apikey'] === 'jimdo'
		) {
			echo "<h1>Yay!!!!!!</h1>";
			echo '<iframe width="420" height="315" src="http://www.youtube.com/embed/1uHbLm4XLR0#t=22s" frameborder="0" allowfullscreen></iframe>';
			die();
		}
	}
}