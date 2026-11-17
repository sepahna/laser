import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  poweredByHeader: false,
  output: "export",
  trailingSlash: true,
  basePath: "/laser",
  images: { unoptimized: true },
};

export default nextConfig;
