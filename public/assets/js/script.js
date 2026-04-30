
document.addEventListener("DOMContentLoaded", () => {
  const startWorkButton = document.getElementById("startWorkButton");
  const popupModal = new bootstrap.Modal(document.getElementById("popupModal"));
  const images = document.querySelectorAll(".row img");

  // Show initial popup modal
  startWorkButton.addEventListener("click", () => {
    popupModal.show();
  });

  // Add click event to each image
  images.forEach((image) => {
    image.addEventListener("click", () => {
      const targetModalId = image.getAttribute("data-target");
      const targetModal = new bootstrap.Modal(
        document.querySelector(targetModalId)
      );
      targetModal.show();
    });
  });
});



function toggleColor(buttonId) {
  const buttons = document.querySelectorAll('.toggle-buttonModal8');

  // Reset all buttons to default state
  buttons.forEach(button => {
    button.style.background = '#F2F2F2';
    button.querySelector('span').style.color = 'gray';
  });

  // Toggle the clicked button
  const clickedButton = document.getElementById(buttonId);
  clickedButton.style.background = '#1CA2ED';
  clickedButton.querySelector('span').style.color = 'white';
}

function toggleColorModal9(buttonId) {
  const buttons = document.querySelectorAll('.toggle-buttonModal9');

  // Reset all buttons to default state
  buttons.forEach(button => {
    button.style.background = '#F2F2F2';
    button.querySelector('span').style.color = 'gray';
  });

  // Toggle the clicked button
  const clickedButton = document.getElementById(buttonId);
  clickedButton.style.background = '#1CA2ED';
  clickedButton.querySelector('span').style.color = 'white';
}

function toggleColorModal12(buttonId) {
  const buttons = document.querySelectorAll('.toggle-buttonModal12');

  // Reset all buttons to default state
  buttons.forEach(button => {
    button.style.background = '#F2F2F2';
    button.querySelector('span').style.color = 'gray';
  });

  // Toggle the clicked button
  const clickedButton = document.getElementById(buttonId);
  clickedButton.style.background = '#1CA2ED';
  clickedButton.querySelector('span').style.color = 'white';
}
function toggleColorModal11(buttonId) {
  const buttons = document.querySelectorAll('.toggle-buttonModal11');

  // Reset all buttons to default state
  buttons.forEach(button => {
    button.style.background = '#F2F2F2';
    button.querySelector('span').style.color = 'gray';
  });

  // Toggle the clicked button
  const clickedButton = document.getElementById(buttonId);
  clickedButton.style.background = '#1CA2ED';
  clickedButton.querySelector('span').style.color = 'white';
}

function toggleColorModal17(buttonId) {
  const buttons = document.querySelectorAll('.toggle-buttonModal17');

  // Reset all buttons to default state
  buttons.forEach(button => {
    button.style.background = '#F2F2F2';
    button.querySelector('span').style.color = 'gray';
  });

  // Toggle the clicked button
  const clickedButton = document.getElementById(buttonId);
  clickedButton.style.background = '#1CA2ED';
  clickedButton.querySelector('span').style.color = 'white';
}


document.addEventListener("DOMContentLoaded", () => {
  const createButton = document.getElementById("createButton");
  createButton.addEventListener("click", () => {
    const currentModal = bootstrap.Modal.getInstance(
      document.getElementById("modal1")
    );
    currentModal.hide();
    const targetModalId = createButton.getAttribute("data-target");
    const targetModal = new bootstrap.Modal(
      document.querySelector(targetModalId)
    );
    targetModal.show();
  });
});


document.addEventListener("DOMContentLoaded", () => {
  const buttons = document.querySelectorAll("#shareButton");

  buttons.forEach((createButton) => {
    createButton.addEventListener("click", () => {
      const currentModal = bootstrap.Modal.getInstance(
        document.getElementById("modal7")
      );
      currentModal.hide();
      const targetModalId = createButton.getAttribute("data-target");
      const targetModal = new bootstrap.Modal(
        document.querySelector(targetModalId)
      );
      targetModal.show();
    });
  });
});

document.addEventListener("DOMContentLoaded", () => {
  const buttons = document.querySelectorAll("#sharebuttonto9");

  buttons.forEach((createButton) => {
    createButton.addEventListener("click", () => {
      const currentModal = bootstrap.Modal.getInstance(
        document.getElementById("modal14")
      );
      currentModal.hide();
      const targetModalId = createButton.getAttribute("data-target");
      const targetModal = new bootstrap.Modal(
        document.querySelector(targetModalId)
      );
      targetModal.show();
    });
  });
});


document.addEventListener("DOMContentLoaded", () => {
  const createButton = document.getElementById("unlimitedButton");
  createButton.addEventListener("click", () => {
    const currentModal = bootstrap.Modal.getInstance(
      document.getElementById("modal2")
    );
    currentModal.hide();
    const targetModalId = createButton.getAttribute("data-target");
    const targetModal = new bootstrap.Modal(
      document.querySelector(targetModalId)
    );
    targetModal.show();
  });
});


document.addEventListener("DOMContentLoaded", () => {
  const createButton = document.getElementById("limitedButton");
  createButton.addEventListener("click", () => {
    const currentModal = bootstrap.Modal.getInstance(
      document.getElementById("modal10")
    );
    currentModal.hide();
    const targetModalId = createButton.getAttribute("data-target");
    const targetModal = new bootstrap.Modal(
      document.querySelector(targetModalId)
    );
    targetModal.show();
  });
});

document.addEventListener("DOMContentLoaded", () => {
  const createButton = document.getElementById("limitedButton2");
  createButton.addEventListener("click", () => {
    const currentModal = bootstrap.Modal.getInstance(
      document.getElementById("modal10")
    );
    currentModal.hide();
    const targetModalId = createButton.getAttribute("data-target");
    const targetModal = new bootstrap.Modal(
      document.querySelector(targetModalId)
    );
    targetModal.show();
  });
});

document.addEventListener("DOMContentLoaded", () => {
  const createButton = document.getElementById("unlimitedButton");
  createButton.addEventListener("click", () => {
    const currentModal = bootstrap.Modal.getInstance(
      document.getElementById("modal2")
    );
    currentModal.hide();
    const targetModalId = createButton.getAttribute("data-target");
    const targetModal = new bootstrap.Modal(
      document.querySelector(targetModalId)
    );
    targetModal.show();
  });
});
document.addEventListener("DOMContentLoaded", () => {
  const createButton = document.getElementById("limitedButton");
  createButton.addEventListener("click", () => {
    const currentModal = bootstrap.Modal.getInstance(
      document.getElementById("modal2")
    );
    currentModal.hide();
    const targetModalId = createButton.getAttribute("data-target");
    const targetModal = new bootstrap.Modal(
      document.querySelector(targetModalId)
    );
    targetModal.show();
  });
});

document.addEventListener("DOMContentLoaded", () => {
  const createButton = document.getElementById("unlimitedButton2");
  createButton.addEventListener("click", () => {
    const currentModal = bootstrap.Modal.getInstance(
      document.getElementById("modal10")
    );
    currentModal.hide();
    const targetModalId = createButton.getAttribute("data-target");
    const targetModal = new bootstrap.Modal(
      document.querySelector(targetModalId)
    );
    targetModal.show();
  });
});
document.addEventListener("DOMContentLoaded", () => {
  const createButton = document.getElementById("limitedButton2");
  createButton.addEventListener("click", () => {
    const currentModal = bootstrap.Modal.getInstance(
      document.getElementById("modal10")
    );
    currentModal.hide();
    const targetModalId = createButton.getAttribute("data-target");
    const targetModal = new bootstrap.Modal(
      document.querySelector(targetModalId)
    );
    targetModal.show();
  });
});

document.addEventListener("DOMContentLoaded", () => {
  const createButton = document.getElementById("sosButton");
  createButton.addEventListener("click", () => {
    const currentModal = bootstrap.Modal.getInstance(
      document.getElementById("modal4")
    );
    currentModal.hide();
    const targetModalId = createButton.getAttribute("data-target");
    const targetModal = new bootstrap.Modal(
      document.querySelector(targetModalId)
    );
    targetModal.show();
  });
});



/**     Surveys  */



document.addEventListener("DOMContentLoaded", () => {
  const createButton = document.getElementById("backButtonToModal12");
  createButton.addEventListener("click", () => {
    const currentModal = bootstrap.Modal.getInstance(
      document.getElementById("modal2")
    );
    currentModal.hide();
    const targetModalId = createButton.getAttribute("data-target");
    const targetModal = new bootstrap.Modal(
      document.querySelector(targetModalId)
    );
    targetModal.show();
  });
});

document.addEventListener("DOMContentLoaded", () => {
  const createButton = document.getElementById("backButtonToModal11");
  createButton.addEventListener("click", () => {
    const currentModal = bootstrap.Modal.getInstance(
      document.getElementById("modal10")
    );
    currentModal.hide();
    const targetModalId = createButton.getAttribute("data-target");
    const targetModal = new bootstrap.Modal(
      document.querySelector(targetModalId)
    );
    targetModal.show();
  });
});

document.addEventListener("DOMContentLoaded", () => {
  const createButton = document.getElementById("backButtonToModal18");
  createButton.addEventListener("click", () => {
    const currentModal = bootstrap.Modal.getInstance(
      document.getElementById("modal12")
    );
    currentModal.hide();
    const targetModalId = createButton.getAttribute("data-target");
    const targetModal = new bootstrap.Modal(
      document.querySelector(targetModalId)
    );
    targetModal.show();
  });
});

document.addEventListener("DOMContentLoaded", () => {
  const createButton = document.getElementById("GoButtonToModal18");
  createButton.addEventListener("click", () => {
    const currentModal = bootstrap.Modal.getInstance(
      document.getElementById("modal11")
    );
    currentModal.hide();
    const targetModalId = createButton.getAttribute("data-target");
    const targetModal = new bootstrap.Modal(
      document.querySelector(targetModalId)
    );
    targetModal.show();
  });
});


document.addEventListener("DOMContentLoaded", () => {
  const createButton = document.getElementById("backButtonToMainFrModel1");
  createButton.addEventListener("click", () => {
    const currentModal = bootstrap.Modal.getInstance(
      document.getElementById("modal4")
    );
    currentModal.hide();
    const targetModalId = createButton.getAttribute("data-target");
    const targetModal = new bootstrap.Modal(
      document.querySelector(targetModalId)
    );
    targetModal.show();
  });
});


document.addEventListener("DOMContentLoaded", () => {
  const createButton = document.getElementById("backButtonToMainFrModel11");
  createButton.addEventListener("click", () => {
    const currentModal = bootstrap.Modal.getInstance(
      document.getElementById("modal2")
    );
    currentModal.hide();
    const targetModalId = createButton.getAttribute("data-target");
    const targetModal = new bootstrap.Modal(
      document.querySelector(targetModalId)
    );
    targetModal.show();
  });
});

document.addEventListener("DOMContentLoaded", () => {
  const createButton = document.getElementById("backButtonToMainFrModel6");
  createButton.addEventListener("click", () => {
    const currentModal = bootstrap.Modal.getInstance(
      document.getElementById("modal6")
    );
    currentModal.hide();
    const targetModalId = createButton.getAttribute("data-target");
    const targetModal = new bootstrap.Modal(
      document.querySelector(targetModalId)
    );
    targetModal.show();
  });
});

document.addEventListener("DOMContentLoaded", () => {
  const createButton = document.getElementById("backButtonToMainFrModel16");
  createButton.addEventListener("click", () => {
    const currentModal = bootstrap.Modal.getInstance(
      document.getElementById("modal16")
    );
    currentModal.hide();
    const targetModalId = createButton.getAttribute("data-target");
    const targetModal = new bootstrap.Modal(
      document.querySelector(targetModalId)
    );
    targetModal.show();
  });
});

document.addEventListener("DOMContentLoaded", () => {
  const createButton = document.getElementById("systemButton");
  createButton.addEventListener("click", () => {
    const currentModal = bootstrap.Modal.getInstance(
      document.getElementById("modal14")
    );
    currentModal.hide();
    const targetModalId = createButton.getAttribute("data-target");
    const targetModal = new bootstrap.Modal(
      document.querySelector(targetModalId)
    );
    targetModal.show();
  });
});

document.addEventListener("DOMContentLoaded", () => {
  const createButton = document.getElementById("backButtonToModal9");
  createButton.addEventListener("click", () => {
    const currentModal = bootstrap.Modal.getInstance(
      document.getElementById("modal9")
    );
    currentModal.hide();
    const targetModalId = createButton.getAttribute("data-target");
    const targetModal = new bootstrap.Modal(
      document.querySelector(targetModalId)
    );
    targetModal.show();
  });
});

document.addEventListener("DOMContentLoaded", () => {
  const createButton = document.getElementById("backButtonToModal4");
  createButton.addEventListener("click", () => {
    const currentModal = bootstrap.Modal.getInstance(
      document.getElementById("modal14")
    );
    currentModal.hide();
    const targetModalId = createButton.getAttribute("data-target");
    const targetModal = new bootstrap.Modal(
      document.querySelector(targetModalId)
    );
    targetModal.show();
  });
});
document.addEventListener("DOMContentLoaded", () => {
  const createButton = document.getElementById("eventButton");
  createButton.addEventListener("click", () => {
    const currentModal = bootstrap.Modal.getInstance(
      document.getElementById("modal5")
    );
    currentModal.hide();
    const targetModalId = createButton.getAttribute("data-target");
    const targetModal = new bootstrap.Modal(
      document.querySelector(targetModalId)
    );
    targetModal.show();
  });
});

document.addEventListener("DOMContentLoaded", () => {
  const createButton = document.getElementById("createSOSButton");
  createButton.addEventListener("click", () => {
    const currentModal = bootstrap.Modal.getInstance(
      document.getElementById("modal6")
    );
    currentModal.hide();
    const targetModalId = createButton.getAttribute("data-target");
    const targetModal = new bootstrap.Modal(
      document.querySelector(targetModalId)
    );
    targetModal.show();
  });
});

document.addEventListener("DOMContentLoaded", () => {
  const createButton = document.getElementById("backButton");
  createButton.addEventListener("click", () => {
    const currentModal = bootstrap.Modal.getInstance(
      document.getElementById("modal1")
    );
    currentModal.hide();
    const targetModalId = createButton.getAttribute("data-target");
    const targetModal = new bootstrap.Modal(
      document.querySelector(targetModalId)
    );
    targetModal.show();
  });
});

document.addEventListener("DOMContentLoaded", () => {
  const createButton = document.getElementById("backButtonToMain");
  createButton.addEventListener("click", () => {
    const currentModal = bootstrap.Modal.getInstance(
      document.getElementById("modal2")
    );
    currentModal.hide();
    const targetModalId = createButton.getAttribute("data-target");
    const targetModal = new bootstrap.Modal(
      document.querySelector(targetModalId)
    );
    targetModal.show();
  });
});

document.addEventListener("DOMContentLoaded", () => {
  const createButton = document.getElementById("backButtonToMainFrLimited");
  createButton.addEventListener("click", () => {
    const currentModal = bootstrap.Modal.getInstance(
      document.getElementById("modal10")
    );
    currentModal.hide();
    const targetModalId = createButton.getAttribute("data-target");
    const targetModal = new bootstrap.Modal(
      document.querySelector(targetModalId)
    );
    targetModal.show();
  });
});

document.addEventListener("DOMContentLoaded", () => {
  const createButton = document.getElementById("backButtonTolimitedFrFinal");
  createButton.addEventListener("click", () => {
    const currentModal = bootstrap.Modal.getInstance(
      document.getElementById("modal11")
    );
    currentModal.hide();
    const targetModalId = createButton.getAttribute("data-target");
    const targetModal = new bootstrap.Modal(
      document.querySelector(targetModalId)
    );
    targetModal.show();
  });
});

document.addEventListener("DOMContentLoaded", () => {
  const createButton = document.getElementById("backButtonToMainFrunlimited");
  createButton.addEventListener("click", () => {
    const currentModal = bootstrap.Modal.getInstance(
      document.getElementById("modal12")
    );
    currentModal.hide();
    const targetModalId = createButton.getAttribute("data-target");
    const targetModal = new bootstrap.Modal(
      document.querySelector(targetModalId)
    );
    targetModal.show();
  });
});

document.addEventListener("DOMContentLoaded", () => {
  const createButton = document.getElementById("backButtonToMainFrModel3");
  createButton.addEventListener("click", () => {
    const currentModal = bootstrap.Modal.getInstance(
      document.getElementById("modal3")
    );
    currentModal.hide();
    const targetModalId = createButton.getAttribute("data-target");
    const targetModal = new bootstrap.Modal(
      document.querySelector(targetModalId)
    );
    targetModal.show();
  });
});

document.addEventListener("DOMContentLoaded", () => {
  const createButton = document.getElementById("backButtonToModal7");
  createButton.addEventListener("click", () => {
    const currentModal = bootstrap.Modal.getInstance(
      document.getElementById("modal8")
    );
    currentModal.hide();
    const targetModalId = createButton.getAttribute("data-target");
    const targetModal = new bootstrap.Modal(
      document.querySelector(targetModalId)
    );
    targetModal.show();
  });
});

document.addEventListener("DOMContentLoaded", () => {
  const createButton = document.getElementById("backButtonToMainFrModel13");
  createButton.addEventListener("click", () => {
    const currentModal = bootstrap.Modal.getInstance(
      document.getElementById("modal13")
    );
    currentModal.hide();
    const targetModalId = createButton.getAttribute("data-target");
    const targetModal = new bootstrap.Modal(
      document.querySelector(targetModalId)
    );
    targetModal.show();
  });
});

document.addEventListener("DOMContentLoaded", () => {
  const createButton = document.getElementById("backButtonToMainFrModel4");
  createButton.addEventListener("click", () => {
    const currentModal = bootstrap.Modal.getInstance(
      document.getElementById("modal4")
    );
    currentModal.hide();
    const targetModalId = createButton.getAttribute("data-target");
    const targetModal = new bootstrap.Modal(
      document.querySelector(targetModalId)
    );
    targetModal.show();
  });
});

document.addEventListener("DOMContentLoaded", () => {
  const createButton = document.getElementById("backButtonToMainFrModel14");
  createButton.addEventListener("click", () => {
    const currentModal = bootstrap.Modal.getInstance(
      document.getElementById("modal14")
    );
    currentModal.hide();
    const targetModalId = createButton.getAttribute("data-target");
    const targetModal = new bootstrap.Modal(
      document.querySelector(targetModalId)
    );
    targetModal.show();
  });
});

document.addEventListener("DOMContentLoaded", () => {
  const createButton = document.getElementById("createSystemButtonFromModal12");
  createButton.addEventListener("click", () => {
    const currentModal = bootstrap.Modal.getInstance(
      document.getElementById("modal12")
    );
    currentModal.hide();
    const targetModalId = createButton.getAttribute("data-target");
    const targetModal = new bootstrap.Modal(
      document.querySelector(targetModalId)
    );
    targetModal.show();
  });
});

document.addEventListener("DOMContentLoaded", () => {
  const createButton = document.getElementById("backButtonToMainFrModel17");
  createButton.addEventListener("click", () => {
    const currentModal = bootstrap.Modal.getInstance(
      document.getElementById("modal17")
    );
    currentModal.hide();
    const targetModalId = createButton.getAttribute("data-target");
    const targetModal = new bootstrap.Modal(
      document.querySelector(targetModalId)
    );
    targetModal.show();
  });
});

document.addEventListener("DOMContentLoaded", () => {
  const createButton = document.getElementById("createSystemButtonFromModal11");
  createButton.addEventListener("click", () => {
    const currentModal = bootstrap.Modal.getInstance(
      document.getElementById("modal11")
    );
    currentModal.hide();
    const targetModalId = createButton.getAttribute("data-target");
    const targetModal = new bootstrap.Modal(
      document.querySelector(targetModalId)
    );
    targetModal.show();
  });
});

document.addEventListener("DOMContentLoaded", () => {
  const buttons = document.querySelectorAll("#backButtonToModal1");
  const thumbnailContainer = document.getElementById("thumbnailContainer");
  const imageCounter = document.getElementById("imageCounter");

  // Function to update the image count
  function updateImageCount(count) {
    imageCounter.textContent = count;
  }

  buttons.forEach((createButton) => {
    createButton.addEventListener("click", () => {
      // Before navigating to another modal, update the image count
      const currentThumbnails = thumbnailContainer.querySelectorAll("img");
      updateImageCount(currentThumbnails.length);

      // Hide the current modal (modal7)
      const currentModal = bootstrap.Modal.getInstance(
        document.getElementById("modal7")
      );
      currentModal.hide();

      // Show the target modal
      const targetModalId = createButton.getAttribute("data-target");
      const targetModal = new bootstrap.Modal(
        document.querySelector(targetModalId)
      );
      targetModal.show();
    });
  });
});


document.addEventListener("DOMContentLoaded", () => {
  const buttons = document.querySelectorAll("#createSurveyInfo");

  buttons.forEach((createButton) => {
    createButton.addEventListener("click", () => {
      const currentModal = bootstrap.Modal.getInstance(
        document.getElementById("modal3")
      );
      currentModal.hide();
      const targetModalId = createButton.getAttribute("data-target");
      const targetModal = new bootstrap.Modal(
        document.querySelector(targetModalId)
      );
      targetModal.show();
    });
  });
});


/*********************************    Tache 1 *********************************************/
document.addEventListener("DOMContentLoaded", () => {
  const addImageButton = document.getElementById("addImageButton");
 // const previewContainerWrapper = document.getElementById("previewContainerWrapper");
  const previewContainerWrapper = document.querySelector(".previewContainerWrapper");
  const modal1 = document.getElementById("modal1");
  const modal7 = document.getElementById("modal7");
  const mainImage = document.getElementById("mainImage");
  const thumbnailContainer = document.getElementById("thumbnailContainer");
  const imageCounter = document.getElementById("imageCounter");
  const deleteButton = document.getElementById("deleteButton");
  const psys3Image = document.getElementById("system-par3");

  const MAX_IMAGES = 5;
  //const DEFAULT_IMAGE = "{{ asset('assets/svg/svg-dialog/second-svg-dialog/image%201425.svg')}}"; // Default image when no images are available

  let thumbnails = []; // Array to store the image sources

  // Validate the file
  function validateFile(file, callback) {
    const allowedTypes = ["image/jpeg", "image/png", "video/mp4"];
    if (!allowedTypes.includes(file.type)) {
      return callback(false, null);
    }

    const reader = new FileReader();

    reader.onload = (e) => {
      const img = new Image();
      img.onload = () => {
        // Resize the image if it exceeds the maximum dimensions
        if (img.width > 350 || img.height > 812) {
          const canvas = document.createElement("canvas");
          const ctx = canvas.getContext("2d");

          // Calculate the new dimensions while preserving the aspect ratio
          const ratio = Math.min(350 / img.width, 812 / img.height);
          const newWidth = img.width * ratio;
          const newHeight = img.height * ratio;

          canvas.width = newWidth;
          canvas.height = newHeight;

          // Draw the resized image onto the canvas
          ctx.drawImage(img, 0, 0, newWidth, newHeight);

          // Get the resized image as a data URL
          const resizedDataUrl = canvas.toDataURL(file.type);

          callback(true, resizedDataUrl); // Pass the resized image to the callback
        } else {
          callback(true, e.target.result); // No resizing needed
        }
      };

      img.src = e.target.result;
    };

    reader.onerror = () => {
      callback(false, null);
    };

    reader.readAsDataURL(file);
  }

  // Handle file input
  function handleFileInput(inputElement) {
    inputElement.addEventListener("change", async (event) => {
      const files = event.target.files;
      const currentThumbnails = thumbnailContainer.querySelectorAll("img");
      if (currentThumbnails.length + files.length > MAX_IMAGES) {
        alert(`You can only upload a maximum of ${MAX_IMAGES} images.`);
        event.target.value = ""; // Clear the file input
        return; // Exit the function if the limit is exceeded
      }

      for (const file of files) {
        if (file) {
          validateFile(file, (isValid, fileSrc) => {
            if (isValid) {
              // Add the first image as the main image
              if (currentThumbnails.length === 0) {
                mainImage.src = fileSrc;
                psys3Image.src = fileSrc;
              }

              // Create a thumbnail and append it to the container
              const thumbnail = document.createElement("img");
              thumbnail.src = fileSrc;
              thumbnail.classList.add("img-thumbnail");
              thumbnail.style.width = "45px";
              thumbnail.style.height = "45px";
              thumbnail.style.cursor = "pointer";
              thumbnail.style.objectFit = "cover";
              thumbnail.style.padding = "0";
              thumbnail.alt = `Thumbnail ${currentThumbnails.length + 1}`;
              thumbnail.addEventListener("click", () => changeMainImage(fileSrc));

              thumbnailContainer.appendChild(thumbnail);

              // Update the image count
              updateImageCount(thumbnailContainer.children.length);
              thumbnails.push(fileSrc); // Add the image source to the array
            }
          });
        }
      }

      // Hide modal1 and show modal7
      const modal1Instance = bootstrap.Modal.getInstance(modal1);
      modal1Instance.hide();

      const modal7Instance = new bootstrap.Modal(modal7);
      modal7Instance.show();
    });
  }

  // Change the main image
  function changeMainImage(src) {
    mainImage.src = src;
    psys3Image.src = src;
  }

  // Update the image count
  function updateImageCount(count) {
    imageCounter.textContent = count;
  }

  // Delete the main image
  deleteButton.addEventListener("click", () => {
    const currentThumbnails = Array.from(thumbnailContainer.querySelectorAll("img"));
    const currentMainImageSrc = mainImage.src;

    // Find the index of the current main image in the thumbnails
    const currentIndex = thumbnails.findIndex((src) => src === currentMainImageSrc);

    if (currentIndex !== -1) {
      // Remove the current thumbnail and image source from the array
      thumbnails.splice(currentIndex, 1);
      currentThumbnails[currentIndex].remove(); // Remove the thumbnail from the DOM

      // Check if there are any thumbnails left
      if (thumbnails.length > 0) {
        // If there are still thumbnails, update the main image to the next available thumbnail
        let nextIndex = (currentIndex === thumbnails.length) ? currentIndex - 1 : currentIndex;
        mainImage.src = thumbnails[nextIndex]; // Set the new main image
        psys3Image.src = thumbnails[nextIndex];
      } else {
        // If no images remain, set to the default image
        mainImage.src = DEFAULT_IMAGE;
        psys3Image.src = DEFAULT_IMAGE;
      }

      // Update the image count
      updateImageCount(thumbnails.length);
    } else {
    }
  });

  // Attach file input handler
  const fileInputs = document.querySelectorAll(".fileInput");
  fileInputs.forEach((fileInput) => {
    fileInput.setAttribute("multiple", "true"); // Allow multiple file selection
    handleFileInput(fileInput);
  });
});


/*************greeeting  */

document.addEventListener("DOMContentLoaded", () => {
  const addImageButton = document.getElementById("addImageButtonModal4");
  //const previewContainerWrapper = document.getElementById("previewContainerWrapper");
  const previewContainerWrapper = document.querySelector(".previewContainerWrapper");
  const modal4 = document.getElementById("modal4");
  const modal14 = document.getElementById("modal14");
  const mainImage = document.getElementById("mainImageModal14");
  const thumbnailContainer = document.getElementById("thumbnailContainer");
  const imageCounter = document.getElementById("imageCounter");
  const deleteButton = document.getElementById("deleteButtonModal14");
  const fileInput = document.querySelector(".fileInput18");

  const MAX_IMAGES = 1;
  //const DEFAULT_IMAGE = "{{ asset('assets/svg/svg-dialog/second-svg-dialog/image%201425.svg')}}"; // Default image when no images are available

  let thumbnails = []; // Array to store the image sources

  // Validate the file
  function validateFile(file, callback) {
    const allowedTypes = ["image/jpeg", "image/png", "video/mp4"];
    if (!allowedTypes.includes(file.type)) {
      return callback(false, null);
    }

    const reader = new FileReader();

    reader.onload = (e) => {
      const img = new Image();
      img.onload = () => {
        // Resize the image if it exceeds the maximum dimensions
        if (img.width > 350 || img.height > 812) {
          const canvas = document.createElement("canvas");
          const ctx = canvas.getContext("2d");

          // Calculate the new dimensions while preserving the aspect ratio
          const ratio = Math.min(350 / img.width, 812 / img.height);
          const newWidth = img.width * ratio;
          const newHeight = img.height * ratio;

          canvas.width = newWidth;
          canvas.height = newHeight;

          // Draw the resized image onto the canvas
          ctx.drawImage(img, 0, 0, newWidth, newHeight);

          // Get the resized image as a data URL
          const resizedDataUrl = canvas.toDataURL(file.type);

          callback(true, resizedDataUrl); // Pass the resized image to the callback
        } else {
          callback(true, e.target.result); // No resizing needed
        }
      };

      img.src = e.target.result;
    };

    reader.onerror = () => {
      callback(false, null);
    };

    reader.readAsDataURL(file);
  }

  // Handle file input
  function handleFileInput(inputElement) {
    inputElement.addEventListener("change", async (event) => {
      console.log("File input change event triggered"); // Debugging point

      const files = event.target.files;
      const currentThumbnails = thumbnailContainer.querySelectorAll("img");
      if (currentThumbnails.length + files.length > MAX_IMAGES) {
        alert(`You can only upload a maximum of ${MAX_IMAGES} images.`);
        event.target.value = ""; // Clear the file input
        return; // Exit the function if the limit is exceeded
      }

      for (const file of files) {
        console.log("Processing file:", file.name);

        if (file) {
          validateFile(file, (isValid, fileSrc) => {
            if (isValid) {
              // Add the first image as the main image
              if (currentThumbnails.length === 0) {
                console.log("Setting main image:", fileSrc);

                mainImage.src = fileSrc;
                psys3Image.src = fileSrc;

              }

              // Create a thumbnail and append it to the container
              const thumbnail = document.createElement("img");
              thumbnail.src = fileSrc;
              thumbnail.classList.add("img-thumbnail");
              thumbnail.style.width = "45px";
              thumbnail.style.height = "45px";
              thumbnail.style.cursor = "pointer";
              thumbnail.style.objectFit = "cover";
              thumbnail.style.padding = "0";
              thumbnail.alt = `Thumbnail ${currentThumbnails.length + 1}`;
              thumbnail.addEventListener("click", () => changeMainImage(fileSrc));

              thumbnailContainer.appendChild(thumbnail);

              // Update the image count
              updateImageCount(thumbnailContainer.children.length);
              thumbnails.push(fileSrc); // Add the image source to the array
            }
          });
        }
      }

      // Hide modal1 and show modal7
      const modal4Instance = bootstrap.Modal.getInstance(modal4);
      modal4Instance.hide();

      const modal14Instance = new bootstrap.Modal(modal14);
      modal14Instance.show();
    });
  }

  // Change the main image
  function changeMainImage(src) {
    mainImage.src = src;
    psys3Image.src = src;

  }

  // Update the image count
  function updateImageCount(count) {
    imageCounter.textContent = count;
  }

  // Delete the main image
  deleteButton.addEventListener("click", () => {
    const currentThumbnails = Array.from(thumbnailContainer.querySelectorAll("img"));
    const currentMainImageSrc = mainImage.src;

    // Find the index of the current main image in the thumbnails
    const currentIndex = thumbnails.findIndex((src) => src === currentMainImageSrc);

    if (currentIndex !== -1) {
      // Remove the current thumbnail and image source from the array
      thumbnails.splice(currentIndex, 1);
      currentThumbnails[currentIndex].remove(); // Remove the thumbnail from the DOM

      // Check if there are any thumbnails left
      if (thumbnails.length > 0) {
        // If there are still thumbnails, update the main image to the next available thumbnail
        let nextIndex = (currentIndex === thumbnails.length) ? currentIndex - 1 : currentIndex;
        mainImage.src = thumbnails[nextIndex]; // Set the new main image
        psys3Image.src = thumbnails[nextIndex];

      } else {
        // If no images remain, set to the default image
        mainImage.src = DEFAULT_IMAGE;
        psys3Image.src = DEFAULT_IMAGE;
      }

      // Update the image count
      updateImageCount(thumbnails.length);
    } else {
    }
  });

  // Attach file input handler
  const fileInputs = document.querySelectorAll(".fileInput4");
  fileInputs.forEach((fileInput) => {
    fileInput.setAttribute("multiple", "true"); // Allow multiple file selection
    handleFileInput(fileInput);
  });
});

/********************Surveys ************ */
document.addEventListener("DOMContentLoaded", () => {
  const addImageButton = document.getElementById("addImageButtonModel6");
  const previewContainerWrapper = document.getElementById("image-preview-container");
  const previewContainerWrapperModel6 = document.getElementById("previewContainerWrapperModel6");
  const descriptionTextContainer = document.getElementById("descriptionTextContainer");
  const fileInput = document.querySelector(".fileInput6");
  const deleteButton = document.getElementById("deleteButton1");
  const MAX_IMAGES = 1;
  let imageCount = 0;



  // Function to validate the file type and size
  function validateFile(file, callback) {
    const allowedTypes = ["image/jpeg", "image/png", "video/mp4"];
    if (!allowedTypes.includes(file.type)) {
      alert("Only JPG, PNG, or MP4 files are allowed.");
      return callback(false, null);
    }

    const reader = new FileReader();
    reader.onload = (e) => {
      const img = new Image();
      img.onload = () => {
        if (img.width > 350 || img.height > 812) {
          const canvas = document.createElement("canvas");
          const ctx = canvas.getContext("2d");

          const ratio = Math.min(350 / img.width, 812 / img.height);
          const newWidth = img.width * ratio;
          const newHeight = img.height * ratio;

          canvas.width = newWidth;
          canvas.height = newHeight;

          ctx.drawImage(img, 0, 0, newWidth, newHeight);
          const resizedDataUrl = canvas.toDataURL(file.type);
          callback(true, resizedDataUrl);
        } else {
          callback(true, e.target.result);
        }
      };
      img.src = e.target.result;
    };

    reader.onerror = () => {
      callback(false, null);
    };

    reader.readAsDataURL(file);
  }

  // Handle file input changes
  fileInput.addEventListener("change", (event) => {
    const files = event.target.files;
    if (imageCount + files.length <= MAX_IMAGES) {
      Array.from(files).forEach((file) => {
        validateFile(file, (isValid, fileData) => {
          if (isValid) {
            console.log("Valid image file loaded, appending it to the container.");

            const previewImage = document.createElement("img");
            previewImage.src = fileData;
            previewImage.alt = "Image Preview";
            previewImage.style.width = "100%";
            previewImage.style.height = "100%";
            previewImage.style.objectFit = "fill";
            previewImage.style.borderRadius = "10px";

            // Append the image to the preview container
            previewContainerWrapper.appendChild(previewImage);

            // Hide the upload interface
            addImageButton.style.display = "none";
            fileInput.style.display = "none";
            descriptionTextContainer.style.display = "none"
            previewContainerWrapperModel6.style.border = "none";


            imageCount++;
            console.log("Image successfully appended to the container.");
          }
        });
      });
    } else {
      alert("You can only upload a maximum of 1 image.");
    }
  });

  // Handle image deletion
  deleteButton.addEventListener("click", () => {
    console.log("Deleting the image...");

    // Remove the preview image
    while (previewContainerWrapper.firstChild) {
      previewContainerWrapper.removeChild(previewContainerWrapper.firstChild);
    }

    // Reset image count
    imageCount = 0;

    // Reshow the upload interface
    addImageButton.style.display = "block";
    fileInput.style.display = "block";
    descriptionTextContainer.style.display = "flex"
    previewContainerWrapperModel6.style.border = "2px dashed gray"; // Restore the border

    $('[name="image"]').val('');
    console.log("Image deleted and upload interface restored.");
  });
});





document.addEventListener("DOMContentLoaded", () => {
  const mp3Input = document.getElementById("Mp3Input");
  const fileInput = document.querySelector(".fileInput7");
  const deleteButtonMp3 = document.getElementById("deleteButtonMp3");
  const previewContainerMp3 = document.getElementById("previewContainerMp3");
  const Mp3upload = document.getElementById("Mp3upload");
  const playButton = document.getElementById("play");
  const durationSpan = document.getElementById("Duration"); // For the original modal
  const modal17Duration = document.getElementById("DurationModal7"); // Target duration in modal 17
  const playIconModal17 = document.querySelector(".overlay img[src='" + playbtn + "']");
  const MAX_DURATION = 300; // Maximum duration in seconds
  const allowedTypes = ["audio/mpeg", "audio/wav"];
  let audioElement = null; // Variable to store the audio element

  // Validate file type and size
  function validateFile(file, callback) {
    if (!allowedTypes.includes(file.type)) {
      alert("Only MP3 or WAV files are allowed.");
      return callback(false, null);
    }

    const audio = new Audio();
    audio.src = URL.createObjectURL(file);

    audio.addEventListener("loadedmetadata", () => {
      if (audio.duration > MAX_DURATION) {
        alert(`The file exceeds the maximum allowed duration of ${MAX_DURATION} seconds.`);
        return callback(false, null);
      }

      callback(true, { file, duration: audio.duration });
    });

    audio.onerror = () => {
      alert("Invalid audio file.");
      callback(false, null);
    };
  }

  // Handle file input change
  fileInput.addEventListener("change", (event) => {
    const files = event.target.files;

    if (files.length > 1) {
      alert("You can only upload one file at a time.");
      return;
    }

    const file = files[0];
    validateFile(file, (isValid, fileData) => {
      if (isValid) {
        // Hide the upload area
        mp3Input.style.display = "none";
        previewContainerMp3.style.border = "none";
        Mp3upload.style.display = "block";

        // Update the duration spans
        durationSpan.textContent = formatDuration(fileData.duration);
        modal17Duration.textContent = formatDuration(fileData.duration);

        // Create an audio element and append it
        if (!audioElement) {
          audioElement = new Audio(URL.createObjectURL(fileData.file));
          audioElement.addEventListener("timeupdate", () => {
            const currentTime = audioElement.currentTime;
            durationSpan.textContent = formatDuration(currentTime);
            modal17Duration.textContent = formatDuration(currentTime);
          });
        } else {
          audioElement.src = URL.createObjectURL(fileData.file);
        }
      }
    });
  });

  // Delete audio file and reset upload area
  deleteButtonMp3.addEventListener("click", () => {
    // Stop audio playback if it's playing
    if (audioElement && !audioElement.paused) {
      audioElement.pause();
    }

    // Reset the audio element and clear the file input
    fileInput.value = ""; // Reset file input
    mp3Input.style.display = "block"; // Reshow the upload area
    mp3Input.style.top = "0px"; // Réinitialise la position verticale
    mp3Input.style.bottom = "0px"; // Réinitialise la position horizontale
    previewContainerMp3.style.border = "2px dashed gray"; // Restore the border
    Mp3upload.style.display = "none";

    // Reset duration span
    durationSpan.textContent = "00:00";

    // Reset audio element
    audioElement = null;

    // Reset play button and icon
    playButton.src = pausebtn;
    playIconModal17.src = playbtn;

    console.log("Audio file deleted.");
  });

  // Play/pause functionality
  function togglePlay() {
    if (audioElement) {
      if (audioElement.paused) {
        audioElement.play();
        playButton.src = playpusecgrnbtn;
        playIconModal17.src = playpusecgrnbtn;
      } else {
        audioElement.pause();
        playButton.src = pausebtn;
        playIconModal17.src = playbtn;
      }
    }
  }

  playButton.addEventListener("click", togglePlay);
  playIconModal17.addEventListener("click", togglePlay);

  // Format duration to "MM:SS"
  function formatDuration(seconds) {
    const minutes = Math.floor(seconds / 60);
    const remainingSeconds = Math.floor(seconds % 60);
    return `${String(minutes).padStart(2, "0")}:${String(remainingSeconds).padStart(2, "0")}`;
  }
});



/*    Donation ***/

document.addEventListener("DOMContentLoaded", () => {
  const addImageButton = document.getElementById("addImageButtonModel2");
  const addImageButton_3 = document.getElementById("addImageButtonModel2_3");
  const addImageButton_4 = document.getElementById("addImageButtonModel2_4");

  const previewContainerWrapper = document.getElementById("image-preview-containerModal2");
  const previewContainerWrapper_3 = document.getElementById("image-preview-containerModal2_3");
  const previewContainerWrapper_4 = document.getElementById("image-preview-containerModal2_4");

  const previewContainerWrapperModel6 = document.getElementById("previewContainerWrapperModel2");
  const previewContainerWrapperModel6_3 = document.getElementById("previewContainerWrapperModel2_3");
  const previewContainerWrapperModel6_4 = document.getElementById("previewContainerWrapperModel2_4");

  const descriptionTextContainer = document.getElementById("descriptionTextContainerModal2");
  const descriptionTextContainer_3 = document.getElementById("descriptionTextContainerModal2_3");
  const descriptionTextContainer_4 = document.getElementById("descriptionTextContainerModal2_4");
  const fileInput = document.querySelector(".fileInput18");
  const deleteButton = document.getElementById("deleteButtonModal2");

  const fileInput_3 = document.querySelector(".fileInput18_3");
  const deleteButton_3 = document.getElementById("deleteButtonModal2_3");

  const fileInput_4 = document.querySelector(".fileInput18_4");
  const deleteButton_4 = document.getElementById("deleteButtonModal2_4");

  const MAX_IMAGES = 1;
  let imageCount = 0;
  const MAX_IMAGES_3 = 1;
  const MAX_IMAGES_4 = 1;
  let imageCount_3 = 0;
  let imageCount_4 = 0;



  // Function to validate the file type and size
  function validateFile(file, callback) {
    const allowedTypes = ["image/jpeg", "image/png", "video/mp4"];
    if (!allowedTypes.includes(file.type)) {
      alert("Only JPG, PNG, or MP4 files are allowed.");
      return callback(false, null);
    }

    const reader = new FileReader();
    reader.onload = (e) => {
      const img = new Image();
      img.onload = () => {
        if (img.width > 350 || img.height > 812) {
          const canvas = document.createElement("canvas");
          const ctx = canvas.getContext("2d");

          const ratio = Math.min(350 / img.width, 812 / img.height);
          const newWidth = img.width * ratio;
          const newHeight = img.height * ratio;

          canvas.width = newWidth;
          canvas.height = newHeight;

          ctx.drawImage(img, 0, 0, newWidth, newHeight);
          const resizedDataUrl = canvas.toDataURL(file.type);
          callback(true, resizedDataUrl);
        } else {
          callback(true, e.target.result);
        }
      };
      img.src = e.target.result;
    };

    reader.onerror = () => {
      callback(false, null);
    };

    reader.readAsDataURL(file);
  }

  // Handle file input changes
  fileInput.addEventListener("change", (event) => {
    const files = event.target.files;

    if (imageCount + files.length <= MAX_IMAGES) {
      Array.from(files).forEach((file) => {
        validateFile(file, (isValid, fileData) => {
          if (isValid) {
            console.log("Valid image file loaded, appending it to the container.");

            const previewImage = document.createElement("img");
            previewImage.src = fileData;
            previewImage.alt = "Image Preview";
            previewImage.style.width = "100%";
            previewImage.style.height = "100%";
            previewImage.style.objectFit = "fill";
            previewImage.style.borderRadius = "10px";

            // Append the image to the preview container
            previewContainerWrapper.appendChild(previewImage);

            // Hide the upload interface
            addImageButton.style.display = "none";
            fileInput.style.display = "none";
            descriptionTextContainer.style.display = "none"
            previewContainerWrapperModel6.style.border = "none";


            imageCount++;
            console.log("Image successfully appended to the container.");
          }
        });
      });
    } else {
      alert("You can only upload a maximum of 1 image.");
    }
  });

  // Handle image deletion
  deleteButton.addEventListener("click", () => {
    console.log("Deleting the image...");

    // Remove the preview image
    while (previewContainerWrapper.firstChild) {
      previewContainerWrapper.removeChild(previewContainerWrapper.firstChild);
    }

    // Reset image count
    imageCount = 0;

    // Reshow the upload interface
    addImageButton.style.display = "block";
    fileInput.style.display = "block";
    descriptionTextContainer.style.display = "flex"
    previewContainerWrapperModel6.style.border = "2px dashed gray"; // Restore the border

    $('[name="image"]').val('');
    console.log("Image deleted and upload interface restored.");
  });


// Handle file input3 changes
fileInput_3.addEventListener("change", (event) => {
  const files = event.target.files;

  if (imageCount_3 + files.length <= MAX_IMAGES_3) {
    Array.from(files).forEach((file) => {
      validateFile(file, (isValid, fileData) => {
        if (isValid) {
          console.log("Valid image file loaded, appending it to the container.");

          const previewImage = document.createElement("img");
          previewImage.src = fileData;
          previewImage.alt = "Image Preview";
          previewImage.style.width = "100%";
          previewImage.style.height = "100%";
          previewImage.style.objectFit = "fill";
          previewImage.style.borderRadius = "10px";

          // Append the image to the preview container
          previewContainerWrapper_3.appendChild(previewImage);

          // Hide the upload interface
          addImageButton_3.style.display = "none";
          fileInput_3.style.display = "none";
          descriptionTextContainer_3.style.display = "none"
          previewContainerWrapperModel6_3.style.border = "none";


          imageCount_3++;
          console.log("Image successfully appended to the container.");
        }
      });
    });
  } else {
    alert("You can only upload a maximum of 1 image.");
  }
});

// Handle3 image deletion
deleteButton_3.addEventListener("click", () => {
  console.log("Deleting the image...");

  // Remove the preview image
  while (previewContainerWrapper_3.firstChild) {
    previewContainerWrapper_3.removeChild(previewContainerWrapper_3.firstChild);
  }

  // Reset image count
  imageCount_3 = 0;

  addImageButton_3.style.display = "block";
  fileInput_3.style.display = "block";
  descriptionTextContainer_3.style.display = "flex"
  previewContainerWrapperModel6_3.style.border = "2px dashed gray"; // Restore the border

  $('[name="image"]').val('');
  console.log("Image deleted and upload interface restored.");
});


// Handle file input4 changes
fileInput_4.addEventListener("change", (event) => {
  const files = event.target.files;

  if (imageCount + files.length <= MAX_IMAGES_4) {
    Array.from(files).forEach((file) => {
      validateFile(file, (isValid, fileData) => {
        if (isValid) {
          console.log("Valid image file loaded, appending it to the container.");

          const previewImage = document.createElement("img");
          previewImage.src = fileData;
          previewImage.alt = "Image Preview";
          previewImage.style.width = "100%";
          previewImage.style.height = "100%";
          previewImage.style.objectFit = "fill";
          previewImage.style.borderRadius = "10px";

          // Append the image to the preview container
          previewContainerWrapper_4.appendChild(previewImage);

          // Hide the upload interface
          addImageButton_4.style.display = "none";
          fileInput_4.style.display = "none";
          descriptionTextContainer_4.style.display = "none"
          previewContainerWrapperModel6_4.style.border = "none";


          imageCount_4++;
          console.log("Image successfully appended to the container.");
        }
      });
    });
  } else {
    alert("You can only upload a maximum of 1 image.");
  }
});

// Handle4 image deletion
deleteButton_4.addEventListener("click", () => {
  console.log("Deleting the image...");

  // Remove the preview image
  while (previewContainerWrapper_4.firstChild) {
    previewContainerWrapper_4.removeChild(previewContainerWrapper_4.firstChild);
  }

  // Reset image count
  imageCount_4 = 0;

  // Reshow the upload interface
  addImageButton_4.style.display = "block";
  fileInput_4.style.display = "block";
  descriptionTextContainer_4.style.display = "flex"
  previewContainerWrapperModel6_4.style.border = "2px dashed gray"; // Restore the border

  $('[name="image"]').val('');
  console.log("Image deleted and upload interface restored.");
});





});


/* Donation Modal 10*/
document.addEventListener("DOMContentLoaded", () => {
  const addImageButton = document.getElementById("addImageButtonModel10");
  const previewContainerWrapper = document.getElementById("image-preview-containerModal10");
  const previewContainerWrapperModel6 = document.getElementById("previewContainerWrapperModel10");
  const descriptionTextContainer = document.getElementById("descriptionTextContainerModal10");
  const fileInput = document.querySelector(".fileInput10");
  const deleteButton = document.getElementById("deleteButtonModal10");
  const MAX_IMAGES = 1;
  let imageCount = 0;



  // Function to validate the file type and size
  function validateFile(file, callback) {
    const allowedTypes = ["image/jpeg", "image/png", "video/mp4"];
    if (!allowedTypes.includes(file.type)) {
      alert("Only JPG, PNG, or MP4 files are allowed.");
      return callback(false, null);
    }

    const reader = new FileReader();
    reader.onload = (e) => {
      const img = new Image();
      img.onload = () => {
        if (img.width > 350 || img.height > 812) {
          const canvas = document.createElement("canvas");
          const ctx = canvas.getContext("2d");

          const ratio = Math.min(350 / img.width, 812 / img.height);
          const newWidth = img.width * ratio;
          const newHeight = img.height * ratio;

          canvas.width = newWidth;
          canvas.height = newHeight;

          ctx.drawImage(img, 0, 0, newWidth, newHeight);
          const resizedDataUrl = canvas.toDataURL(file.type);
          callback(true, resizedDataUrl);
        } else {
          callback(true, e.target.result);
        }
      };
      img.src = e.target.result;
    };

    reader.onerror = () => {
      callback(false, null);
    };

    reader.readAsDataURL(file);
  }

  // Handle file input changes
  fileInput.addEventListener("change", (event) => {
    const files = event.target.files;
    if (imageCount + files.length <= MAX_IMAGES) {
      Array.from(files).forEach((file) => {
        validateFile(file, (isValid, fileData) => {
          if (isValid) {
            console.log("Valid image file loaded, appending it to the container.");

            const previewImage = document.createElement("img");
            previewImage.src = fileData;
            previewImage.alt = "Image Preview";
            previewImage.style.width = "100%";
            previewImage.style.height = "100%";
            previewImage.style.objectFit = "fill";
            previewImage.style.borderRadius = "10px";

            // Append the image to the preview container
            previewContainerWrapper.appendChild(previewImage);

            // Hide the upload interface
            addImageButton.style.display = "none";
            fileInput.style.display = "none";
            descriptionTextContainer.style.display = "none"
            previewContainerWrapperModel6.style.border = "none";


            imageCount++;
            console.log("Image successfully appended to the container.");
          }
        });
      });
    } else {
      alert("You can only upload a maximum of 1 image.");
    }
  });

  // Handle image deletion
  deleteButton.addEventListener("click", () => {
    console.log("Deleting the image...");

    // Remove the preview image
    while (previewContainerWrapper.firstChild) {
      previewContainerWrapper.removeChild(previewContainerWrapper.firstChild);
    }

    // Reset image count
    imageCount = 0;

    // Reshow the upload interface
    addImageButton.style.display = "block";
    fileInput.style.display = "block";
    descriptionTextContainer.style.display = "flex"
    previewContainerWrapperModel6.style.border = "2px dashed gray"; // Restore the border

    $('[name="image"]').val('');
    console.log("Image deleted and upload interface restored.");
  });
});

document.addEventListener("DOMContentLoaded", () => {
  const mp3Input = document.getElementById("Mp3InputModal2");
  const fileInput = document.querySelector(".fileInputModal2");
  const deleteButtonMp3 = document.getElementById("deleteButtonMp3Modal2");
  const previewContainerMp3 = document.getElementById("previewContainerMp3Modal2");
  const Mp3upload = document.getElementById("Mp3uploadModal2");
  const playButton = document.getElementById("playModal2");
  const durationSpan = document.getElementById("DurationModal2"); // For the original modal
  const modal17Duration = document.getElementById("DurationModal7"); // Target duration in modal 17
  const playIconModal17 = document.querySelector(".overlay img[src='"+ playbtn +"']");
  const MAX_DURATION = 300; // Maximum duration in seconds
  const allowedTypes = ["audio/mpeg", "audio/wav"];
  let audioElement = null; // Variable to store the audio element

  // Validate file type and size
  function validateFile(file, callback) {
    if (!allowedTypes.includes(file.type)) {
      alert("Only MP3 or WAV files are allowed.");
      return callback(false, null);
    }

    const audio = new Audio();
    audio.src = URL.createObjectURL(file);

    audio.addEventListener("loadedmetadata", () => {
      if (audio.duration > MAX_DURATION) {
        alert(`The file exceeds the maximum allowed duration of ${MAX_DURATION} seconds.`);
        return callback(false, null);
      }

      callback(true, { file, duration: audio.duration });
    });

    audio.onerror = () => {
      alert("Invalid audio file.");
      callback(false, null);
    };
  }

  // Handle file input change
  fileInput.addEventListener("change", (event) => {
    const files = event.target.files;

    if (files.length > 1) {
      alert("You can only upload one file at a time.");
      return;
    }

    const file = files[0];
    validateFile(file, (isValid, fileData) => {
      if (isValid) {
        // Hide the upload area
        mp3Input.style.display = "none";
        previewContainerMp3.style.border = "none";
        Mp3upload.style.display = "block";

        // Update the duration spans
        durationSpan.textContent = formatDuration(fileData.duration);
        modal17Duration.textContent = formatDuration(fileData.duration);

        // Create an audio element and append it
        if (!audioElement) {
          audioElement = new Audio(URL.createObjectURL(fileData.file));
          audioElement.addEventListener("timeupdate", () => {
            const currentTime = audioElement.currentTime;
            durationSpan.textContent = formatDuration(currentTime);
            modal17Duration.textContent = formatDuration(currentTime);
          });
        } else {
          audioElement.src = URL.createObjectURL(fileData.file);
        }
      }
    });
  });

  // Delete audio file and reset upload area
  deleteButtonMp3.addEventListener("click", () => {
    // Stop audio playback if it's playing
    if (audioElement && !audioElement.paused) {
      audioElement.pause();
    }

    // Reset the audio element and clear the file input
    fileInput.value = ""; // Reset file input
    mp3Input.style.display = "block"; // Reshow the upload area
    mp3Input.style.top = "0px"; // Réinitialise la position verticale
    mp3Input.style.bottom = "0px"; // Réinitialise la position horizontale
    previewContainerMp3.style.border = "2px dashed gray"; // Restore the border
    Mp3upload.style.display = "none";

    // Reset duration span
    durationSpan.textContent = "00:00";

    // Reset audio element
    audioElement = null;

    // Reset play button and icon
    playButton.src = pausebtn;
    playIconModal17.src = playbtn;

    console.log("Audio file deleted.");
  });

  // Play/pause functionality
  function togglePlay() {
    if (audioElement) {
      if (audioElement.paused) {
        audioElement.play();
        playButton.src = playpusecgrnbtn;
        playIconModal17.src = playpusecgrnbtn;
      } else {
        audioElement.pause();
        playButton.src = pausebtn;
        playIconModal17.src = playbtn;
      }
    }
  }

  playButton.addEventListener("click", togglePlay);
  playIconModal17.addEventListener("click", togglePlay);

  // Format duration to "MM:SS"
  function formatDuration(seconds) {
    const minutes = Math.floor(seconds / 60);
    const remainingSeconds = Math.floor(seconds % 60);
    return `${String(minutes).padStart(2, "0")}:${String(remainingSeconds).padStart(2, "0")}`;
  }
});

function changeMainImage(src) {
  const mainImage = document.getElementById("mainImage");
  mainImage.src = src;
  psys3Image.src = src;

}



function updateLabelWithImage(event, containerId) {
  const fileInput = event.target;
  const file = fileInput.files[0];
  const iconContainer = document.getElementById(containerId);

  if (file && file.type.startsWith('image/')) {
    const reader = new FileReader();

    reader.onload = function (e) {
      iconContainer.innerHTML = `<img src="${e.target.result}" alt="Uploaded Image" style="width: 100%; height: 100%; object-fit: contain; border-radius: 4px;" />`;
    };

    reader.readAsDataURL(file);
  } else {
    iconContainer.innerHTML = `<img src="/assets/Gallery%20Add.svg" alt="Icon" />`;
  }
}


