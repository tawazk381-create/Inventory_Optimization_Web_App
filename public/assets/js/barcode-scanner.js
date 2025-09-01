// File: public/assets/js/barcode-scanner.js
// Lightweight barcode scanner: prefers BarcodeDetector API, falls back gracefully.

function startBarcodeScanner(onSuccess, onError) {
  onError = onError || function (e) {
    console.error(e);
    alert("Error: " + e);
  };

  // ✅ Prefer BarcodeDetector if available
  if (window.BarcodeDetector) {
    const formats = ['qr_code', 'ean_13', 'ean_8', 'code_128', 'upc_e', 'upc_a'];
    const detector = new BarcodeDetector({ formats });

    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
      .then(stream => {
        const video = document.createElement('video');
        video.setAttribute('playsinline', '');
        video.style.position = "fixed";
        video.style.top = "50%";
        video.style.left = "50%";
        video.style.transform = "translate(-50%, -50%)";
        video.style.width = "80%";
        video.style.maxWidth = "400px";
        video.style.zIndex = "10000";
        video.style.border = "2px solid #007bff";
        video.style.borderRadius = "8px";
        document.body.appendChild(video);

        video.srcObject = stream;
        video.onloadedmetadata = () => video.play();

        const poll = setInterval(() => {
          detector.detect(video).then(barcodes => {
            if (barcodes.length) {
              clearInterval(poll);
              stream.getTracks().forEach(t => t.stop());
              video.remove();
              onSuccess(barcodes[0].rawValue);
            }
          }).catch(err => {
            clearInterval(poll);
            stream.getTracks().forEach(t => t.stop());
            video.remove();
            onError(err);
          });
        }, 500);
      })
      .catch(err => {
        console.warn("Camera access denied or unavailable.", err);
        fallbackPrompt(onSuccess);
      });
    return;
  }

  // ❌ BarcodeDetector not supported → fallback
  fallbackPrompt(onSuccess);
}

/**
 * Fallback → Prompt user to use wireless scanner instead of camera.
 */
function fallbackPrompt(onSuccess) {
  alert("This browser does not support camera barcode scanning.\n\n👉 Please connect your wireless barcode scanner and scan the item.");
  
  // Listen for scanner input (scanner types into focused field like a keyboard)
  const inputListener = (e) => {
    if (e.target.tagName === "INPUT" || e.target.tagName === "SELECT" || e.target.isContentEditable) {
      // Let normal typing happen inside fields
      return;
    }

    // Accumulate scanned characters
    if (!window._scannerBuffer) window._scannerBuffer = "";
    window._scannerBuffer += e.key;

    // Many scanners send "Enter" at the end
    if (e.key === "Enter") {
      const code = window._scannerBuffer.trim();
      window._scannerBuffer = "";
      if (code) {
        document.removeEventListener("keypress", inputListener);
        onSuccess(code);
      }
    }
  };

  document.addEventListener("keypress", inputListener);
}
