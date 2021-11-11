<?php

function template(){
	?>
	<script>
		function resetVideo(video_id) {

		}
	</script>
	<?php
}

function resetVideoJS(){
	?>
	<script>
		function resetVideo(video_id) {
			var video = document.getElementById(video_id);
			var src = video.src.replace('?autoplay=1', '');

			video.src = '';
			video.dataset.src = src;
		}
	</script>
	<?php
}

function playVideoJS(){
	?>
	<script>
		function playVideo(video_id) {
			var video = document.getElementById(video_id);
			var src = video.dataset.src;
			video.src = src;
		}
	</script>
	<?php
}

function showDIV_JS(){
	?>
	<script>
		function showDIV(DIV_ID) {
			var x = document.getElementById(DIV_ID);
			if (x.style.display === "none") {
				x.style.display = "block";
				playVideo('v'+DIV_ID);
			}else{
				x.style.display = "none";
				resetVideo('v'+DIV_ID);
			}
		}
	</script>
	<?php
}

function copytoclipJS(){
	?>
	<script>
		function copytoclip(link) {
			var dummy = document.createElement("textarea");
			document.body.appendChild(dummy);
			dummy.value = link;
			dummy.select();
			document.execCommand("copy");
			document.body.removeChild(dummy);
		}
	</script>
	<?php
}

?>