import { signal, effect } from '@preact/signals';

/**
 * playlistVisibilitySignal - Signal to control playlist visibility in VideoPlayer
 * Syncs with localStorage for persistence across sessions
 */

// Initialize from localStorage (stored as JSON string)
const getInitialValue = () => {
  try {
    const stored = localStorage.getItem('embedPlaylist');
    return stored ? JSON.parse(stored) : true;
  } catch (err) {
    console.warn('playlistVisibility: Error reading from localStorage:', err);
    return true;
  }
};

export const playlistVisibilitySignal = signal(getInitialValue());

// Sync signal changes to localStorage
effect(() => {
  try {
    localStorage.setItem('embedPlaylist', JSON.stringify(playlistVisibilitySignal.value));
  } catch (err) {
    console.warn('playlistVisibility: Error writing to localStorage:', err);
  }
});

/**
 * Toggle playlist visibility
 */
export const togglePlaylistVisibility = () => {
  playlistVisibilitySignal.value = !playlistVisibilitySignal.value;
};
