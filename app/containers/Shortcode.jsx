import React, { useState, useEffect } from "react"

export default function Shortcode(props) {
  const [isLoading, setIsLoading] = useState("true")
  return (
    <div>
      <p>Is Loading: {isLoading}
        <button onClick={() => setIsLoading("false")}>Test</button>
      </p>
    </div>
  );
}