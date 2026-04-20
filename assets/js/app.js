(function () {
    $(function () {
        $('.datatable').DataTable();
        $('.select2').select2({ width: '100%' });

        $('.delete-btn').on('click', function (e) {
            e.preventDefault();
            const href = $(this).attr('href');
            Swal.fire({
                title: 'Are you sure?',
                text: 'This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = href;
                }
            });
        });

        const success = $('meta[name="flash-success"]').attr('content');
        const error = $('meta[name="flash-error"]').attr('content');
        if (success) toastr.success(success);
        if (error) toastr.error(error);
    });
})();
