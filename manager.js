/*
 * Manager code
 *
 * Author: Alexandre Kaspar
 * Based on: pico-editor
 */

///////////////////////////////////////////////////////////////////////////////
///// File filters ////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////
function applyFilter() {
  var filters = [];
  $('#sidebar .tree-filter option:selected').each(function(){
    filters.push($(this).val());
  });
  $('#sidebar .nav li').each(function(){
    var path = $(this).data('path');
    var valid = true;
    if(filters.length > 0){
      valid = false;
      for(var i = 0; i < filters.length; ++i){
        if(path.indexOf(filters[i]) == 0){
          valid = true;
          break;
        }
      }
    }
    if(valid) $(this).show();
    else $(this).hide();
  });
}
function updateFilter() {
  $.post('admin/tree/list', {}, function(data){
    var $select = $('.tree-filter');
    $select.find('option').remove();

    for(var dir in data){
      $select.each(function(){
        var path = $(this).data('isfilter') ? (dir.length ? '/' + dir : dir) : '/' + dir;
        $(this).append('<option value="' + path + '">' + path + '</option>');
      });
    }
    $select.trigger('chosen:updated');
  }, 'json');
}


///////////////////////////////////////////////////////////////////////////////
///// Media panel /////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////
function mediaAction(event){
  if(event) event.preventDefault();
  // the dom elements
  $link = $(this);
  var p = $link.parent();

  if($link.hasClass('link')){
    // insert link in current document
    editor.getElement('editor').body.innerHTML += '<br />[Awesome link](' + p.data('url') + ')<br />';
    if(!unsaved){
      unsaved = true;
      document.title += ' *';
    }

    // we go back to the editor
    $('#medias').fadeOut('slow');

  } else if($link.hasClass('view')) {
    // open target in new window
    window.open(p.data('url'));

  } else if($link.hasClass('rename')) {
    // rename file
    var newName = prompt('Enter the new file name:', p.data('name'));
    var nameCheck = /[a-zA-Z0-9-_. ()]+/i;
    if(nameCheck.test(newName) && newName.length > 3){
      var oldName = p.data('name');
      $.post('admin/media/rename', {
        file: p.data('page'),
        oldName: oldName,
        newName: newName
      }, function(data){
        console.log('Rename data: ' + data);
        if(data == 'Success'){
          // effective renaming
          p.data('name', newName);
          p.data('url', p.data('url').replace(oldName, newName));
          p.find('.name').text(newName);
        }
      });
    }
  } else if($link.hasClass('delete')){
    // delete file
    if(!confirm('Are you sure you want to delete this file?')) return false;
    $.post('admin/media/delete', {
      file: p.data('page'),
      name: p.data('name')
    }, function(data){
      if(data == 'Success'){
        // delete in UI
        p.remove();
      }
    });

  } else {
    // invalid link?
  }
}

function loadMedias(currentUrl){
  if(!currentUrl) currentUrl = $('#medias').data('url');
  console.log(currentUrl);
  // empty list
  var $list = $('#medias ul');
  $list.empty();
  // get file list
  $.post(base_url + '/admin/media/list', { file: currentUrl }, function(data) {
    $.each(data.list, function(index, file){
      var json = {
        name: file,
        page: data.file,
        dir: data.dir
      };
      var imgExt = /.+\.(png|jpe?g|gif)$/i;
      var tmpl = imgExt.test(file) ? '#medias #img-tpl' : '#medias #file-tpl';
      $res = $(tmpl).nanotmpl(json);
      $res.find('a').click(mediaAction);
      $res.appendTo($list);
    });
  }, 'json');
}

function openMedias(e){
  e.preventDefault();
  //var currentUrl = $(this).attr('data-url');
  var dataUrl = $(this).data('url');
  var currentUrl = dataUrl;
  if(!dataUrl){
    currentUrl = base_url + '/';
    $('#sidebar .nav .open').each(function(){
      currentUrl = base_url + $(this).data('path');
    });
  }
  $('#medias').data('url', currentUrl).fadeIn('slow');
  loadMedias(currentUrl);
}

///////////////////////////////////////////////////////////////////////////////
///// Directory tree //////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////


$(function() {
  $('.tree-filter').each(function(){
    // parameters depend on data
    $(this).chosen({ width: $(this).data('width') || '' });
  });
  $('#sidebar .tree-filter').change(applyFilter);
  updateFilter();
  var unsaved = false;
  var editor = new EpicEditor({
    container: 'epiceditor',
    basePath: base_url + '/plugins/' + yocto_dir + '/epiceditor',
    clientSideStorage: false,
    file: {
      name: 'epiceditor',
      defaultContent: '',
      autoSave: false // 5000
    },
    theme: {
      base: '/themes/base/epiceditor.css',
      preview: '/themes/preview/github.css',
      editor: '/themes/editor/epic-light.css'
    },
    button: {
      preview: true,
      fullscreen: false
    },
    focusOnLoad: true
  }).load();

  $(editor.getElement('editor')).on('keyup', function (){
    if(!unsaved){
      unsaved = true;
      document.title += ' *';
    }
  });

  // New
  $('.controls .new').on('click', function(e){
    e.preventDefault();
    var linkName = prompt('Please enter page to create', '/');
    if(!linkName) return false; // cancelled by user
    // check that the link is correct
    if(linkName.charAt(0) != '/'){
      alert('Page should start with /');
      return false;
    }
    var splitIndex = linkName.lastIndexOf('/');
    var dir = linkName.substring(0, splitIndex + 1);
    var title = linkName.substring(splitIndex + 1);

    if(title != null && title != '' && dir != null && dir != ''){
      $.post('admin/new', { title: title, dir: dir }, function(data){
        if(data.error){
          alert(data.error);
        } else {
          $('#sidebar .nav .open').removeClass('open');
          $('#epiceditor').data('currentFile', data.file);
          editor.importFile('epiceditor', data.content);
          unsaved = false;
          document.title = document.title.replace(' *', '');
          $('.nav').prepend('<li><a href="#" data-url="' + base_url + data.file +'" class="post open"><span data-icon="3" aria-hidden="true"></span>'+ data.file +'</a><a href="' + base_url + data.file +'" target="_blank" class="view" title="View">5</a><a href="#" data-url="' + base_url + data.file + '" class="media" title="Media Manager">8</a><a href="#" data-url="' + base_url + data.file +'" class="delete" title="Delete">4</a></li>');
        }
      }, 'json');
    }
  });

  // Open post
  $('.nav').on('click', '.post', function(e){
    e.preventDefault();
    if(unsaved && !confirm('You have unsaved changes. Are you sure you want to leave this post?')) return false;
    $('#sidebar .nav .open').removeClass('open');
    $(this).parent().addClass('open');

    var fileUrl = $(this).attr('data-url');
    $.post('admin/open', { file: fileUrl }, function(data){
      $('#epiceditor').data('currentFile', fileUrl);
      editor.importFile('epiceditor', data);
      unsaved = false;
      document.title = document.title.replace(' *', '');
      });
    });

  // Save post
  editor.on('autosave', function () {
    $('#saving').text('Saving...').addClass('active');
    $.post('admin/save', { file: $('#epiceditor').data('currentFile'), content: editor.exportFile() }, function(data){
      $('#saving').text('Saved');
      unsaved = false;
      document.title = document.title.replace(' *', '');
      setTimeout(function(){
        $('#saving').removeClass('active');
      }, 1000);
    });
  });

  // Save on preview
  editor.on('preview', function () {
    editor.save();
    editor.emit('autosave'); // only time when we really save!
  });

  // Delete post
  $('.nav').on('click', '.delete', function(e){
    e.preventDefault();
    if(!confirm('Are you sure you want to delete this file?')) return false;
    $('.nav .post').removeClass('open');

    var li = $(this).parents('li');
    var fileUrl = $(this).attr('data-url');
    $.post('admin/delete', { file: fileUrl }, function(data){
      li.remove();
      $('#epiceditor').data('currentFile', '');
      editor.importFile('epiceditor', '');
      unsaved = false;
      document.title = document.title.replace(' *', '');
    });
  });

  $('#sidebar .controls .tree').click(function(){

  });

  // Open media
  $('.nav').on('click', '.media', openMedias);
  $('#sidebar .controls .media').click(openMedias);
  $('#medias').click(function(event){
    if($(event.target).attr('id') == 'medias'){
      $(this).fadeOut('slow');
    }
  });

  // Upload
  var fileCount = 0;
  var fileDone = 0;
  $('#fileupload').fileupload({
    dataType: 'json'
  }).bind('fileuploadchange', function(e, data){ // FILE CHANGE ---------
    // we update the file count
    fileCount = data.files.length;

  }).bind('fileuploadadd', function(e, data) { // QUEUE ADD -------------
    // we add the "loading" item template
    $('#medias #load-tpl').nanotmpl({
      name: data.files[0].name,
      id: data.files[0].name
    }).appendTo($('#medias ul'));

  }).bind('fileuploadsubmit', function(e, data) { // SUBMIT -------------
    data.formData = {
      file: $('#medias').data('url')
    };

  }).bind('fileuploadprogress', function(e, data) { // PROGRESS ---------
    var progress = parseInt(data.loaded / data.total * 100, 10);
    var id = data.files[0].name;
    $('#medias li.loading').filter(function(){
      return $(this).data('id') == id;
    }).find('.bar').css('width', progress + '%');

  }).bind('fileuploadprogressall', function(e, data) { // PROGRESSALL ---
    var progress = parseInt(data.loaded / data.total * 100, 10);
    $('#medias .progress').fadeIn('slow');
    $('#medias .progress .bar').css('width', progress + '%');

  }).bind('fileuploaddone', function(e, data) { // DONE -----------------
    // TODO take care of failure cases
    // (look at data.result or data.textStatus != 'success')
    // increment done counter
    ++fileDone;
    if(fileDone >= fileCount) {
      // hide if we're fully done, and reload everything
      $('#medias .progress').fadeOut('slow', reload);
    } else {
      if(data.result && data.result.medias){
        var file = data.result.medias[0];
        if(file){
          $('#medias li.loading').filter(function(){
            return $(this).data('id') == file.name;
          }).removeClass('loading').find('.bar').remove();
        }
      }
    }
  });

  // Window resize
  $('body,#main,#epiceditor').height($(window).height());
  $(window).resize(function() {
    $('body,#main,#epiceditor').height($(window).height());
    editor.reflow();
  });

  // -- end of onLoad code
});

