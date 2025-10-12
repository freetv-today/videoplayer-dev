import VideoPlayer from '../components/videoplayer.jsx';
import { useEffect } from 'preact/hooks';

export function Home() {

    useEffect(() => {
		document.title = "Free TV: Home";
	}, []);

    // Test identifiers from Internet Archive:
    // 
    // king-of-the-hill_202103
    // bananaman-the-complete-collection-1983-86-series-1-3
    // mr.-men-1974-78-bbc-tv-series-the-complete-collection

    const item = {
        "identifier":"mr.-men-1974-78-bbc-tv-series-the-complete-collection",
        "ontrack": null
    } 

    // Dev Note: onTrack callback not currently used for anything
    return (<VideoPlayer identifier={item.identifier} onTrack={item.ontrack} />);
}