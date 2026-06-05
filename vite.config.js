import tailwindcss from "@tailwindcss/vite";
import laravel from "laravel-vite-plugin";
import { defineConfig } from "vite";
import { compression, defineAlgorithm } from "vite-plugin-compression2";

export default defineConfig({
	plugins: [
		laravel({
			input: [
				"resources/css/app.css",
				"resources/js/app.js",
				"resources/css/filament/admin/theme.css",
			],
			refresh: true,
		}),
		tailwindcss(),
		compression({
			algorithms: [
				"gzip",
				"zstd",
				"brotliCompress",
				defineAlgorithm("deflate", { level: 9 }),
			],
		}),
	],
	server: {
		watch: {
			ignored: ["**/storage/framework/views/**"],
		},
	},
});
