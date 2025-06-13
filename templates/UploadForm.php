<?php if ( is_user_logged_in() ) : ?>
  <form id="ead-artwork-upload-form" enctype="multipart/form-data">
    <label>Title <input type="text" name="title" required></label><br>
    <label>Description <textarea name="description"></textarea></label><br>
    <label>Image <input type="file" name="image" accept="image/*" required></label><br>
    <button type="submit">Upload Artwork</button>
  </form>
  <div id="ead-upload-feedback"></div>
<?php else : ?>
  <p>Please log in to upload artwork.</p>
<?php endif; ?>
