document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('.ap-submission-form');
  if (!form) return;

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const formData = new FormData(form);
    const postType = form.dataset.postType || 'artpulse_event';

    const title = formData.get('title');
    const eventDate = formData.get('event_date');
    const eventLocation = formData.get('event_location');
    const images = form.querySelector('#ap-images').files;

    const imageIds = [];

    try {
      // Upload each image and get the media ID
      for (const file of images) {
        const mediaId = await uploadMedia(file);
        imageIds.push(mediaId);
      }

      const submission = {
        post_type: postType,
        title: title,
        event_date: eventDate,
        event_location: eventLocation,
        image_ids: imageIds
      };

      const res = await fetch(APSubmission.endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': APSubmission.nonce
        },
        body: JSON.stringify(submission)
      });

      const data = await res.json();

      if (res.ok) {
        alert('Submission successful!');
        console.log(data);
      } else {
        alert(data.message || 'Submission failed.');
      }
    } catch (err) {
      console.error(err);
      alert('Error: ' + err.message);
    }
  });

  async function uploadMedia(file) {
    const formData = new FormData();
    formData.append('file', file);

    const response = await fetch(APSubmission.mediaEndpoint, {
      method: 'POST',
      headers: {
        'X-WP-Nonce': APSubmission.nonce
      },
      body: formData
    });

    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.message || 'Image upload failed');
    }

    const result = await response.json();
    return result.id;
  }
});
