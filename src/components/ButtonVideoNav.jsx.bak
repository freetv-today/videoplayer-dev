import { useLocalStorage } from '@/hooks/useLocalStorage';
import { confirmPlaylistReload } from '@/utils';
import { useDebugLog } from '@hooks/useDebugLog';

// Yellow nav buttons which appear on NowPlaying screen
// The Episode Playlist toggle button controls whether the
// playlist from Internet Archive (&playlist=1) is shown or not
export function ButtonVideoNav() {

  const log = useDebugLog();
  const [embedPlaylist, setEmbedPlaylist] = useLocalStorage('embedPlaylist', true);

  let btnState = 'Off';
  if (embedPlaylist) {
    btnState = 'On';
  }

  const handlePlaylistToggle = (e) => {
    // Prevent the checkbox from toggling automatically
    e.preventDefault();
    const newValue = !embedPlaylist;
    if (confirmPlaylistReload()) {
      setEmbedPlaylist(newValue);
      log('Episode Playlist button has been toggled');
      setTimeout('window.location.reload();', 500);
    }
  };

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
      <input
        id="playlistBtn"
        type="checkbox"
        className="btn-check"
        autoComplete="off"
        checked={embedPlaylist}
        onClick={handlePlaylistToggle}
        readOnly
      />
      <label
        className="btn btn-sm btn-outline-warning fw-bold"
        htmlFor="playlistBtn"
        title={`Episode Playlist is ${btnState}`}
      >
        Episode Playlist
      </label>
    </span>
  );
}