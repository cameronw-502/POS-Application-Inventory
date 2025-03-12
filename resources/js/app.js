import "./bootstrap";
import React from "react";
import { createRoot } from "react-dom/client";
import PosApp from "./components/Pos/PosApp";

const container = document.getElementById("pos-root");
if (container) {
    const root = createRoot(container);
    root.render(<PosApp />);
}
