import { useDebugLog } from '@hooks/useDebugLog';
// import { playlistVisibilitySignal, togglePlaylistVisibility } from '@signals/playlistVisibility';

// Yellow nav buttons which appear on NowPlaying screen
// The Episode Playlist toggle button controls whether the
// playlist in the VideoJS player is shown or hidden
// Uses Signal for state management - no prop drilling needed!
export function ButtonVideoNav() {

  const log = useDebugLog();
  
  // Access signal value to make component reactive
  // const isVisible = playlistVisibilitySignal.value;
  
  // console.log('🔘 ButtonVideoNav render - playlist visible:', isVisible);

  // const handlePlaylistToggle = (e) => {
  //   togglePlaylistVisibility();
  //   console.log('🔄 Toggle clicked - new value:', playlistVisibilitySignal.value);
  //   log(`Episode Playlist toggled: ${playlistVisibilitySignal.value ? 'On' : 'Off'}`);
  // };

  return (
    <span>
      <button
        id="backBtn"
        className="btn btn-sm btn-outline-warning fw-bold ms-2 me-2"
        title="Go back to the previous page"
        onClick={() => window.history.back()}
      >
        &larr; Back
      </button>
      {/* <input
        id="playlistBtn"
        type="checkbox"
        className="btn-check"
        autoComplete="off"
        checked={isVisible}
        onChange={handlePlaylistToggle}
      />
      <label
        className={`btn btn-sm ${isVisible ? 'btn-warning' : 'btn-outline-warning'} fw-bold`}
        htmlFor="playlistBtn"
        title={`Episode Playlist is ${isVisible ? 'On' : 'Off'}`}
      >
        Episode Playlist
      </label> */}
    </span>
  );
}