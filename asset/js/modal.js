
function openModal(type, title, body, action)
{
    switch (type) {
        case 'okcansel':
            $('#OKCanselModal .modal-title').html(title);
            $('#OKCanselModal .modal-body').html(body);

            if (action != null) {
                $('#OKCanselModal .f-action').attr('onclick', action);
            }
            $('#OKModal').modal('show');

            break;
        case 'ok':
            $('#OKModal .modal-title').html(title);
            $('#OKModal .modal-body').html(body);

            if (action != null) {
                $('#OKModal .f-action').attr('onclick', action);
            }
            $('#OKModal').modal('show');
            break;
    }
}

function closeModal()
{
    $('#OKCanselModal').modal('hide');
    $('#OKModal').modal('hide');
}
