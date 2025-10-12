import { useEffect } from "preact/hooks";

export function NotFound() {
	useEffect(() => {
			document.title = "404: Not Found";
	}, []);
	
	return (<h1 className="fw-bold pt-5 text-center text-light">404: Not Found</h1>);
}
