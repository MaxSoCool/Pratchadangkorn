document.addEventListener('DOMContentLoaded', function() {
    const existingFilesList = document.getElementById('existing-files-list');

    if (existingFilesList) {
        existingFilesList.addEventListener('click', function(event) {
            // Check if the clicked element is a 'remove-existing-file' button
            if (event.target.classList.contains('remove-existing-file')) {
                // Find the parent 'input-group' div and remove it from the DOM
                const fileItem = event.target.closest('.existing-file-item');
                if (fileItem) {
                    fileItem.remove();
                    // When the file item is removed, its associated hidden input (`existing_file_paths_retained[]`)
                    // is also removed. This means its path will NOT be sent in the POST request,
                    // signaling to the backend that this file should be deleted.
                }
            }
        });
    }
});