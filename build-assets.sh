#!/bin/bash
# Build Vite assets for production

set -e

echo "Building Vite assets..."
echo "========================"

# Check if we're in the right directory
if [ ! -f package.json ]; then
    echo "Error: package.json not found. Run this script from the project root."
    exit 1
fi

# Check if node_modules exists
if [ ! -d node_modules ]; then
    echo "Installing npm dependencies..."
    npm install
fi

# Build assets
echo "Running: npm run build"
npm run build

echo ""
echo "Build complete!"
echo "Build artifacts: ./public/build/"
ls -lh public/build/

# Verify manifest.json was created
if [ ! -f public/build/manifest.json ]; then
    echo "ERROR: manifest.json was not created!"
    exit 1
fi

echo ""
echo "✓ Assets built successfully"
echo "✓ Vite manifest.json is ready"
