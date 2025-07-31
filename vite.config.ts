import { defineConfig } from "vite";
import { svelte } from "@sveltejs/vite-plugin-svelte";

export default defineConfig({
  plugins: [svelte()],
  build: {
    outDir: "dist",
    rollupOptions: {
      input: "src/main.ts",
      output: {
        entryFileNames: "ai-chatbot.js",
        assetFileNames: (assetInfo) => {
          if (assetInfo.name && assetInfo.name.endsWith(".css")) {
            return "ai-chatbot.css";
          }
          return assetInfo.name || "assets/[name].[ext]";
        },
      },
    },
  },
});
