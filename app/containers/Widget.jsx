import React, { useState, useEffect } from "react"

export default function Widget(props) {
  const [isLoading, setIsLoading] = useState("true")

  return (
    <div>
      <h1>WP Reactivate Widget</h1>
      <p>Title: {props.wpObject.title} {isLoading}
        <button onClick={() => setIsLoading("false")}>Test</button>
      </p>
    </div>
  );
}
