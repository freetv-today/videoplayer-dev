import { useEffect } from "preact/hooks";

export function Test() {
    
    useEffect(() => {
		document.title = "Free TV: Test Page";
	}, []);

    return (
        <div className="container-fluid w-100 bg-white text-center" style={{height: '99vh'}}>
            <h1 className="py-5 fw-bold">Test Page</h1>
            <img src="/freetv.png" width="300" alt="Free TV" />
            <p className="mt-4"><a href="/" target="_top" className="btn btn-outline-primary p-3 fw-bold shadow">Back to Home</a></p>
            {/* Insert test code here */}
        </div>
        
    )
}