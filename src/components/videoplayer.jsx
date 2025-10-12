import { useState, useEffect, useRef } from 'preact/hooks';
import videojs from 'video.js';
import 'video.js/dist/video-js.css';
import '@videojs/themes/dist/fantasy/index.css';
import 'videojs-playlist';

// Pass onTrack callback if you want parent-level handling
const VideoPlayer = ({ identifier, onTrack }) => { 

  const videoRef = useRef(null);
  const [player, setPlayer] = useState(null);
  const [playlist, setPlaylist] = useState([]);
  const [loading, setLoading] = useState(true);
  const [showResume, setShowResume] = useState(false);
  const [savedTime, setSavedTime] = useState(0);
  const [showPlaylist, setShowPlaylist] = useState(true); // For collapsible playlist
  const saveInterval = useRef(null);
  const progressPoints = [0.25, 0.5, 0.75]; // For tracking milestones

  // Seek if resuming (after user confirms via overlay)
  const handleResume = (resume) => {
    setShowResume(false);
    if (resume && player) {
      player.currentTime(savedTime);
    }
    localStorage.removeItem(`resume-${identifier}`); // Clear if not resuming
  };

  // Fetch metadata
  useEffect(() => {
    fetch(`https://archive.org/metadata/${identifier}/files`)
      .then(res => res.json())
      .then(data => {
        const videos = data.result
          .filter(file => file.format && (file.format.includes('h.264') || file.format.includes('WebM')))
          .sort((a, b) => (a.title || a.name) > (b.title || b.name) ? 1 : -1)
          .map(file => ({
            sources: [{ src: `https://archive.org/download/${identifier}/${file.name}`, type: file.name.endsWith('.mp4') ? 'video/mp4' : 'video/webm' }],
            title: file.title || file.name.replace(/\.[^/.]+$/, '')
          }));
        setPlaylist(videos);
        setLoading(false);
      })
      .catch(error => {
        console.error('Failed to fetch playlist:', error);
        setLoading(false);
      });

    return () => {
      if (player) player.dispose();
      if (saveInterval.current) clearInterval(saveInterval.current);
    };
  }, [identifier]);

  // Initialize player and resume logic
  useEffect(() => {
    if (!videoRef.current || !playlist.length) return;

    const vjsPlayer = videojs(videoRef.current, {
      fluid: false,
      responsive: false,
      fill: true,
      playbackRates: [0.5, 1, 1.25, 1.5, 2]
    });

    // @ts-ignore - playlist method added by videojs-playlist plugin
    vjsPlayer.playlist(playlist);

    setPlayer(vjsPlayer);

    // Resume: Check saved time on load
    const loadListener = () => {
      const storageKey = `resume-${identifier}`;
      const saved = parseFloat(localStorage.getItem(storageKey) || '0');
      const duration = vjsPlayer.duration();
      if (saved > 0 && saved < duration && (saved / duration > 0.1)) { // >10% threshold
        setSavedTime(saved);
        setShowResume(true); // Trigger overlay
      }
    };
    vjsPlayer.on('loadedmetadata', loadListener);

    // Save progress
    const saveProgress = () => {
      const current = vjsPlayer.currentTime();
      const duration = vjsPlayer.duration();
      if (current > 0 && current < duration) {
        localStorage.setItem(`resume-${identifier}`, current.toString());
      }
    };
    saveInterval.current = setInterval(saveProgress, 30000); // Every 30s
    vjsPlayer.on('pause', saveProgress); // Save on pause

    // Seek if resuming (after user confirms via overlay)
    const handleResume = (resume) => {
      setShowResume(false);
      if (resume && vjsPlayer) {
        vjsPlayer.currentTime(savedTime);
      }
      localStorage.removeItem(`resume-${identifier}`); // Clear if not resuming
    };

    // Tracking: Events for analytics
    vjsPlayer.on('play', () => {
      // Log view start
      if (onTrack) onTrack('play', identifier);
    });

    vjsPlayer.on('ended', () => {
      // Log completion
      if (onTrack) onTrack('complete', identifier);
      localStorage.removeItem(`resume-${identifier}`); // Clear on finish
    });

    // Debounced progress tracking (e.g., for milestones)
    let lastProgress = 0;
    const trackProgress = () => {
      const current = vjsPlayer.currentTime() / vjsPlayer.duration();
      const nextPoint = progressPoints.find(p => p > lastProgress && current >= p);
      if (nextPoint) {
        lastProgress = nextPoint;
        if (onTrack) onTrack('progress', identifier, Math.round(nextPoint * 100));
      }
    };
    vjsPlayer.on('timeupdate', trackProgress);

    // Cleanup
    return () => {
      vjsPlayer.off('loadedmetadata', loadListener);
      vjsPlayer.off('play');
      vjsPlayer.off('ended');
      vjsPlayer.off('timeupdate', trackProgress);
      vjsPlayer.off('pause');
      clearInterval(saveInterval.current);
    };
  }, [playlist, identifier, onTrack]);

  if (loading) return <div class="loading" style={{textAlign:'center'}}>Loading playlist...</div>;

  return (
    <>
      {/* Test Navbar */}
      <nav class="navbar" data-bs-theme="dark">
        <div class="container-fluid">
          <a class="navbar-brand">
            <img src="/freetv.png" height="40" alt="Free TV" class="me-1 pb-1" />
            Free TV
          </a>
          <a href="/test" target="_top" className="ms-auto me-4 btn btn-primary">Test Page</a>
          <span class="me-lg-5 me-sm-2">
            <input 
              type="checkbox" 
              class="btn-check" 
              id="btn-check" 
              autocomplete="off" 
              checked={showPlaylist}
              onInput={(e) => setShowPlaylist(e.currentTarget.checked)}
            />
            <label class="btn btn-outline-warning" for="btn-check" title="Show/Hide Playlist">Episode Playlist</label>
          </span>
        </div>
      </nav>
      {/* VideoJS Player */}
      <div class="custom-ia-player">
        <div class="video-wrapper" data-vjs-player>
          <video
            id="vidplayer"
            ref={videoRef}
            class="video-js vjs-big-play-centered fantasy-skin"
            controls
            preload="auto"
            data-setup="{}"
          >
            {/* <source src="/colorbars.mp4" type="video/mp4" /> */}
          </video>
          {/* Sample Overlay: Resume prompt */}
          {showResume && (
            <div class="overlay-resume">
              <div class="overlay-content">
                <p>Resume from {Math.round(savedTime / 60)} min?</p>
                <button onClick={() => handleResume(true)}>Yes</button>
                <button onClick={() => handleResume(false)}>Start Over</button>
              </div>
            </div>
          )}
          {/* Add more overlays here, e.g., custom buttons */}
        </div>
        
        <div class={`playlist-wrapper ${showPlaylist ? '' : 'playlist-hidden'}`}>
          <div class="playlist-content">
            <ul class="playlist-list">
              {playlist.map((item, idx) => (
                <li key={idx} onClick={() => { if (player) { player.src(item.sources); player.load(); player.play(); document.title = item.title; } }}>
                  <span>{item.title}</span>
                </li>
              ))}
            </ul>
          </div>
        </div>
      </div>
    </>
  );
};

export default VideoPlayer;