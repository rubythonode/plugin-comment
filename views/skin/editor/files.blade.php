<div class="comment_file_list">
    <!-- [D] 클릭시 클래스 on 적용 -->
    <a href="#" class="btn_file __xe_comment_btn_toggle_file">첨부파일 <strong class="file_num">{{ count($files) }}</strong></a>
    <ul>
        @foreach($files as $file)
            <li>
                {{--@can('download', $instance)--}}
                <a href="{{ route('editor.file.download', ['id' => $file->id])}}">
                {{--@else--}}
                {{--<a href="#">--}}
                {{--@endcan--}}
                    <i class="xi-download-disk"></i> {{ $file->clientname }} <span class="file_size">({{ bytes($file->size) }})</span>
                </a>
            </li>
        @endforeach
    </ul>
</div>