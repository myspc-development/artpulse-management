import React, { useState } from 'react';

export default function SubmissionForm() {
  const [title, setTitle] = useState('');
  const [eventDate, setEventDate] = useState('');
  const [location, setLocation] = useState('');
  const [images, setImages] = useState([]);
  const [previews, setPreviews] = useState([]);
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState('');

  const handleFileChange = (e) => {
    const files = Array.from(e.target.files).slice(0, 5);
    setImages(files);
    setPreviews(files.map(file => URL.createObjectURL(file)));
  };

  const uploadMedia = async (file) => {
    const formData = new FormData();
    formData.append('file', file);

    const res = await fetch(APSubmission.mediaEndpoint, {
      method: 'POST',
      headers: {
        'X-WP-Nonce': APSubmission.nonce
      },
      body: formData
    });

    const json = await res.json();
    if (!res.ok) throw new Error(json.message || 'Upload failed');
    return json.id;
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setMessage('');

    try {
      const imageIds = [];
      for (const file of images) {
        const id = await uploadMedia(file);
        imageIds.push(id);
      }

      const res = await fetch(APSubmission.endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': APSubmission.nonce
        },
        body: JSON.stringify({
          post_type: 'artpulse_event',
          title,
          event_date: eventDate,
          event_location: location,
          image_ids: imageIds
        })
      });

      const json = await res.json();
      if (!res.ok) throw new Error(json.message || 'Submission failed');

      setMessage('Submission successful!');
      setTitle('');
      setEventDate('');
      setLocation('');
      setImages([]);
      setPreviews([]);
    } catch (err) {
      console.error(err);
      setMessage(`Error: ${err.message}`);
    } finally {
      setLoading(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="p-4 max-w-xl mx-auto rounded-xl shadow bg-white space-y-4">
      <h2 className="text-xl font-bold">Submit New Event</h2>

      <input
        className="w-full p-2 border rounded"
        type="text"
        placeholder="Event Title"
        value={title}
        onChange={e => setTitle(e.target.value)}
        required
      />

      <input
        className="w-full p-2 border rounded"
        type="date"
        value={eventDate}
        onChange={e => setEventDate(e.target.value)}
        required
      />

      <input
        className="w-full p-2 border rounded"
        type="text"
        placeholder="Location"
        value={location}
        onChange={e => setLocation(e.target.value)}
        required
      />

      <input
        className="w-full"
        type="file"
        multiple
        accept="image/*"
        onChange={handleFileChange}
      />

      <div className="flex gap-2 flex-wrap">
        {previews.map((src, i) => (
          <img key={i} src={src} alt="" className="w-24 h-24 object-cover rounded border" />
        ))}
      </div>

      <button
        className="bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700"
        type="submit"
        disabled={loading}
      >
        {loading ? 'Submitting...' : 'Submit'}
      </button>

      {message && <p className="text-sm text-center text-gray-700 mt-2">{message}</p>}
    </form>
  );
}
