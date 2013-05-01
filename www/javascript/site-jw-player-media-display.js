/**
 * Class for managing media players
 *
 * @copyright 2011-2013 silverorange
 */
function SiteJwPlayerMediaDisplay(media_id)
{
	this.media_id = media_id;

	this.sources = [];
	this.images  = [];
	this.valid_mime_types = [];

	this.skin = null;
	this.stretching = null;
	this.image = null;
	this.duration = null;
	this.aspect_ratio = null;
	this.start_position = 0;
	this.record_end_point = false;
	this.space_to_pause = false;
	this.swf_uri = null;

	this.upgrade_message = null;
	this.on_complete_message = null;
	this.resume_message =
		'<p>You’ve previously watched part of this video.</p>' +
		'<p>Would you like to:</p>';

	// whether or not to show the on-complete-message when the video loads.
	// this is useful if you want to remind the user they've seen the video
	// before
	this.display_on_complete_message_on_load = false;

	this.on_ready_event = new YAHOO.util.CustomEvent('on_ready', this, true);
	this.end_point_recorded_event =
		new YAHOO.util.CustomEvent('end_point_recorded', this, true);

	this.place_holder = document.createElement('div');
	this.seek_done = false;

	SiteJwPlayerMediaDisplay.players.push(this);

	YAHOO.util.Event.onDOMReady(this.init, this, true);
}

SiteJwPlayerMediaDisplay.current_player_id = null;
SiteJwPlayerMediaDisplay.record_interval = 30; // in seconds
SiteJwPlayerMediaDisplay.players = [];

// {{{ SiteJwPlayerMediaDisplay.prototype.init = function()

SiteJwPlayerMediaDisplay.prototype.init = function()
{
	this.container = document.getElementById('media_display_' + this.media_id);

	if (this.isVideoSupported()) {
		this.embedPlayer();
		this.drawDialogs();
	} else {
		var upgrade = document.createElement('div');
		upgrade.className = 'video-player-upgrade';
		upgrade.innerHTML = this.upgrade_message;
		this.container.appendChild(upgrade);

		var that = this;
		function resizeUpgradeContainer() {
			var region = YAHOO.util.Dom.getRegion(that.container);
			var container_height = region.width / that.aspect_ratio;
			that.container.style.position = 'relative';
			that.container.style.height = container_height + 'px';

			var upgrade_height = YAHOO.util.Dom.getRegion(upgrade).height;
			upgrade.style.position = 'absolute';
			YAHOO.util.Dom.setStyle(upgrade, 'top',
				((container_height - upgrade_height) / 2) + 'px');
		}

		YAHOO.util.Event.on(window, 'resize', resizeUpgradeContainer);
		resizeUpgradeContainer();
	}
};

// }}}
// {{{ SiteJwPlayerMediaDisplay.prototype.embedPlayer = function()

SiteJwPlayerMediaDisplay.prototype.embedPlayer = function()
{
	this.player_id = this.container.childNodes[0].id; 

	this.player = jwplayer(this.player_id).setup( {
		playlist: [{
			image: this.getImage(),
			sources: this.getSources() 
		}],
		skin:        this.skin,
		stretching:  this.stretching,
		primary:     'flash', // to allow for RTMP streaming
		width:       '100%',
		height:      this.getPlayerHeight(),
		flashplayer: this.swf_uri,
		ga:          {} // this can be blank. JW Player will use the _gaq var.
	});

	//this.debug();

	var that = this;
	this.player.onReady(function() {
		that.on_ready_event.fire(that);
	});

	this.player.onFullscreen(function (e) {
		if (e.fullscreen) {
			that.handleFullscreen();
		}
	});

	YAHOO.util.Event.on(window, 'resize', function() {
		this.setPlayerDimensions();
	}, this, true);

	if (this.record_end_point == true) {
		this.recordEndPoint();
	}

	if (this.space_to_pause) {
		this.handleSpaceBar();
	}

	this.player.onBeforePlay(function() {
		SiteJwPlayerMediaDisplay.current_player_id = that.player_id;
	});

	// there's a strange jwplayer side-effect that can cause a buffering
	// video to not pause, thus switching videos can make two players
	// play at the same time.
	function checkIfCurrent() {
		var all_players = SiteJwPlayerMediaDisplay.players;
		for (var i = 0; i < all_players.length; i++) {
			if (all_players[i].player_id !=
				SiteJwPlayerMediaDisplay.current_player_id) {

				all_players[i].pause();
			}
		}
	}

	this.player.onPlay(checkIfCurrent);
	this.player.onPlay(function() {
		if (that.overlay !== null) {
			that.overlay.style.display = 'none';
		}
	});
};

// }}}
// {{{ SiteJwPlayerMediaDisplay.prototype.isVideoSupported = function()

SiteJwPlayerMediaDisplay.prototype.isVideoSupported = function()
{
	// check to see if HTML5 video tag is supported
	var video_tag = document.createElement('video');

	var html5_video = false;
	if (video_tag.canPlayType) {
		for (var i = 0; i < this.valid_mime_types.length; i++) {
			var mime_type = this.valid_mime_types[i];
			if (video_tag.canPlayType(mime_type).replace(/no/, '')) {
				html5_video = true;
				break;
			}
		}
	}

	var flash10 = typeof(YAHOO.util.SWFDetect) === 'undefined'
		|| YAHOO.util.SWFDetect.isFlashVersionAtLeast(10);

	return (flash10 || html5_video);
};

// }}}
// {{{ SiteJwPlayerMediaDisplay.prototype.addSource = function()

SiteJwPlayerMediaDisplay.prototype.addSource = function(
	source_uri, width, label)
{
	this.sources.push({file: source_uri, label: label, width: width});
};

// }}}
// {{{ SiteJwPlayerMediaDisplay.prototype.addImage = function()

SiteJwPlayerMediaDisplay.prototype.addImage = function(
	image_uri, width)
{
	this.images.push({uri: image_uri, width: width});
};

// }}}
// {{{ SiteJwPlayerMediaDisplay.prototype.getSources = function()

SiteJwPlayerMediaDisplay.prototype.getSources = function()
{
	var region = YAHOO.util.Dom.getRegion(this.container);
	var player_width = region.width;

	var default_source = null;
	var min_diff = null;
	for (var i = 0; i < this.sources.length; i++) {
		if (!this.sources[i].width) {
			continue;
		}

		var diff = Math.abs(this.sources[i].width - player_width);
		if (min_diff === null || diff < min_diff) {
			min_diff = diff;
			default_source = i;
		}
	}

	if (default_source !== null) {
		this.sources[default_source]['default'] = true;
	}

	return this.sources;
};

// }}}
// {{{ SiteJwPlayerMediaDisplay.prototype.getImage = function()

SiteJwPlayerMediaDisplay.prototype.getImage = function()
{
	var region = YAHOO.util.Dom.getRegion(this.container);
	var player_width = region.width;

	var default_image = null;
	var min_diff = null;
	for (var i = 0; i < this.images.length; i++) {
		var diff = Math.abs(this.images[i].width - player_width);
		if (min_diff === null || diff < min_diff) {
			min_diff = diff;
			default_image = i;
		}
	}

	return (default_image === null) ? null : this.images[default_image].uri;
};

// }}}
// {{{ SiteJwPlayerMediaDisplay.prototype.addValidMimeType = function()

SiteJwPlayerMediaDisplay.prototype.addValidMimeType = function(mime_type)
{
	this.valid_mime_types.push(mime_type);
};

// }}}
// {{{ SiteJwPlayerMediaDisplay.prototype.recordEndPoint = function()

SiteJwPlayerMediaDisplay.prototype.recordEndPoint = function()
{
	var that = this;

	var interval = SiteJwPlayerMediaDisplay.record_interval;
	var current_position = 0;
	var old_position = 0;

	function recordEndPoint() {
		function callback(response) {
			that.end_point_recorded_event.fire(response);
		}

		var client = new XML_RPC_Client('xml-rpc/media-player');
		client.callProcedure('recordEndPoint', callback,
			[that.media_id, current_position], ['int', 'double']);

		old_position = current_position;
	}

	function autoRecordEndPoint(ev) {
		current_position = ev.position;

		if (current_position > old_position + interval) {
			recordEndPoint();
		}
	}

	this.player.onTime(autoRecordEndPoint);
	this.player.onPause(recordEndPoint);
};

// }}}
// {{{ SiteJwPlayerMediaDisplay.prototype.play = function()

SiteJwPlayerMediaDisplay.prototype.play = function()
{
	// pause other videos on the page
	this.pauseAll();

	// player not available yet or overlay shown
	if (this.overlay.style.display == 'block') {
		return;
	}

	this.player.play(true);
};

// }}}
// {{{ SiteJwPlayerMediaDisplay.prototype.pause = function()

SiteJwPlayerMediaDisplay.prototype.pause = function()
{
	// Both play() and pause() are toggles for jwplayer API unless state is
	// passed. pause(true) doesn't work correctly and is still a toggle, so
	// use play(false) instead.
	this.player.play(false);
};

// }}}
// {{{ SiteJwPlayerMediaDisplay.prototype.pauseAll = function()

SiteJwPlayerMediaDisplay.prototype.pauseAll = function()
{
	var i = 0;
	while (typeof jwplayer(i) !== 'undefined' &&
		typeof jwplayer(i).play !== 'undefined') {

		jwplayer(i).play(false);
		i++;
	}
};

// }}}
// {{{ SiteJwPlayerMediaDisplay.prototype.onPlay = function()

SiteJwPlayerMediaDisplay.prototype.onPlay = function(callback)
{
	this.player.onPlay(callback);
};

// }}}
// {{{ SiteJwPlayerMediaDisplay.prototype.getPlayerHeight = function()

SiteJwPlayerMediaDisplay.prototype.getPlayerHeight = function()
{
	var region = YAHOO.util.Dom.getRegion(this.container);
	return parseInt(region.width / this.aspect_ratio);
};

// }}}
// {{{ SiteJwPlayerMediaDisplay.prototype.setPlayerDimensions = function()

SiteJwPlayerMediaDisplay.prototype.setPlayerDimensions = function()
{
	this.player.resize('100%', this.getPlayerHeight());
};

// }}}
// {{{ SiteJwPlayerMediaDisplay.prototype.handleFullscreen = function()

SiteJwPlayerMediaDisplay.prototype.handleFullscreen = function()
{
	var quality = this.player.getCurrentQuality();
	var width = Math.max(screen.width, screen.height);
	var levels = this.player.getQualityLevels();

	for (var i = levels.length - 1; i >= 0; i--) {
		if (levels[i].width < width && quality != i) {
			this.player.setCurrentQuality(i);
			break;
		}
	}
};

// }}}
// {{{ SiteJwPlayerMediaDisplay.prototype.handleSpaceBar = function()

SiteJwPlayerMediaDisplay.prototype.handleSpaceBar = function()
{
	YAHOO.util.Event.on(document, 'keydown', function (e) {
		var target = YAHOO.util.Event.getTarget(e);

		// don't capture keyboard events for inputs
		var tag = target.tagName.toLowerCase();
		if (tag == 'textarea' || tag == 'input' ||
			this.player_id != SiteJwPlayerMediaDisplay.current_player_id) {
			return;
		}

		if (YAHOO.util.Event.getCharCode(e) == 32) {
			// toggle between play/pause
			this.player.play();
			YAHOO.util.Event.preventDefault(e);
		}
	}, this, true);
};

// }}}
// {{{ SiteJwPlayerMediaDisplay.prototype.debug = function()

SiteJwPlayerMediaDisplay.prototype.debug = function()
{
	var debug_container = document.createElement('div');
	debug_container.style.padding = '4px';
	debug_container.style.position = 'absolute';
	debug_container.style.top = 0;
	debug_container.style.left = 0;
	debug_container.style.backgroundColor = 'rgba(255, 255, 255, 0.7)';
	this.container.appendChild(debug_container);

	var that = this;

	this.player.onMeta(function (v) {
		var meta = v.metadata;
		if (!meta.hasOwnProperty('bufferfill') ||
			!meta.hasOwnProperty('bandwidth')) {

			return;
		}

		var quality_levels = that.player.getQualityLevels();
		var current_level = quality_levels[meta.qualitylevel];
		debug_container.innerHTML = 'player-width: ' + meta.screenwidth + 'px' +
			'<br />transitioning: ' + ((meta.transitioning) ? 'yes' : 'no') +
			'<br />buffer-fill: ' + meta.bufferfill + 's' +
			'<br />quality-level: <strong>' + current_level.label +
				'</strong> (' + meta.qualitylevel + ')' +
			'<br />bandwidth: ' + Math.round(meta.bandwidth / 1024, 2) +
				' Mb/s (' + meta.bandwidth + ')';
	});
};

// }}}

// dialogs
// {{{ SiteJwPlayerMediaDisplay.prototype.drawDialogs = function()

SiteJwPlayerMediaDisplay.prototype.drawDialogs = function()
{
	this.overlay = document.createElement('div');
	this.overlay.style.display = 'none';
	this.overlay.className = 'overlay';
	this.overlay.appendChild(document.createTextNode(''));
	this.container.appendChild(this.overlay);

	// if the video has been watched before, and we're more than 60s from the
	// end or start, show a message allowing the viewed to resume or start
	// from the beginning
	if (this.start_position > 0) {
		this.appendResumeMessage();

		var that = this;
		this.player.onReady(function () {
			if (that.start_position > 60 &&
				that.start_position < that.duration - 60) {

				that.displayResumeMessage();
			}
		});
	}

	// when the video is complete, show a message to resume or go elsewhere
	if (this.on_complete_message !== null) {
		this.appendCompleteMessage();

		if (this.display_on_complete_message_on_load &&
			this.start_position > this.duration - 60) {
			this.displayCompleteMessage();
		}

		var that = this;
		this.player.onComplete(function () {
			that.displayCompleteMessage();
		});
	}
};

// }}}
// {{{ SiteJwPlayerMediaDisplay.prototype.appendCompleteMessage = function()

SiteJwPlayerMediaDisplay.prototype.appendCompleteMessage = function()
{
	this.complete_overlay = document.createElement('div');
	this.complete_overlay.style.display = 'none';
	this.complete_overlay.className = 'overlay-content';
	this.complete_overlay.innerHTML = this.on_complete_message;

	var restart_link = document.createElement('a');
	restart_link.href = '#';
	restart_link.className = 'restart-video';
	restart_link.appendChild(document.createTextNode('Watch Again'));

	var that = this;
	YAHOO.util.Event.on(restart_link, 'click', function (e) {
		YAHOO.util.Event.preventDefault(e);
		that.overlay.style.display = 'none';
		that.complete_overlay.style.display = 'none';
		that.play();
	});

	this.complete_overlay.appendChild(restart_link);

	this.overlay.parentNode.appendChild(this.complete_overlay);
};

// }}}
// {{{ SiteJwPlayerMediaDisplay.prototype.appendResumeMessage = function()

SiteJwPlayerMediaDisplay.prototype.appendResumeMessage = function()
{
	this.resume_overlay = document.createElement('div');
	this.resume_overlay.style.display = 'none';
	this.resume_overlay.className = 'overlay-content';
	this.resume_overlay.innerHTML = this.resume_message;

	var minutes = Math.floor(this.start_position / 60);
	var seconds = this.start_position % 60;
	seconds = (seconds < 10 ? '0' : '') + seconds;

	var resume_link = document.createElement('a');
	resume_link.href = '#';
	resume_link.className = 'resume-video';
	resume_link.appendChild(document.createTextNode(
		'Resume Where You Left Off (' + minutes + ':' + seconds + ')'));

	var that = this;
	YAHOO.util.Event.on(resume_link, 'click', function (e) {
		YAHOO.util.Event.preventDefault(e);
		that.overlay.style.display = 'none';
		that.resume_overlay.style.display = 'none';
		that.seek(that.start_position);
	});

	this.resume_overlay.appendChild(resume_link);

	var restart_link = document.createElement('a');
	restart_link.href = '#';
	restart_link.className = 'restart-video';
	restart_link.appendChild(document.createTextNode(
		'Start From the Beginning'));

	YAHOO.util.Event.on(restart_link, 'click', function (e) {
		YAHOO.util.Event.preventDefault(e);
		that.overlay.style.display = 'none';
		that.resume_overlay.style.display = 'none';
		that.play();
	});

	this.resume_overlay.appendChild(restart_link);

	this.overlay.parentNode.appendChild(this.resume_overlay);
};

// }}}
// {{{ SiteJwPlayerMediaDisplay.prototype.displayCompleteMessage = function()

SiteJwPlayerMediaDisplay.prototype.displayCompleteMessage = function()
{
	this.overlay.style.display = 'block';
	this.complete_overlay.style.display = 'block';
};

// }}}
// {{{ SiteJwPlayerMediaDisplay.prototype.displayResumeMessage = function()

SiteJwPlayerMediaDisplay.prototype.displayResumeMessage = function()
{
	this.overlay.style.display = 'block';
	this.resume_overlay.style.display = 'block';
};

// }}}
// {{{ SiteJwPlayerMediaDisplay.prototype.seek = function()

SiteJwPlayerMediaDisplay.prototype.seek = function(position)
{
	var that = this;

	if (YAHOO.env.ua.ios) {
		this.player.onTime(function(e) {
			if (!that.seek_done && e.position > 1) {
				that.player.seek(position);
				that.seek_done = true;
			}
		});

		this.play();
	} else {
		this.player.seek(position);
	}
};

// }}}
