<x-app-layout>
    @php
        $isEdit = $template->exists;
        $action = $isEdit ? route('admin.plugins.lms.templates.update', $template, false) : route('admin.plugins.lms.templates.store', [], false);
        $isSelectedCatalog = $isEdit && (int) $selectedCatalogId === (int) $template->id;
        $isSelectedDetails = $isEdit && (int) $selectedDetailsId === (int) $template->id;
        $isSelectedDefault = $isSelectedCatalog || $isSelectedDetails;
    @endphp

    <div class="lms-template-studio">
        <header class="lts-head">
            <div><h1>{{ __('lms::ui.auto_b3ef2a236e') }}</h1><p>{{ __('lms::ui.auto_e2a1b4ff8d') }}</p></div>
            <a class="lts-button secondary" href="{{ route('admin.plugins.lms.templates.index', [], false) }}">{{ __('lms::ui.auto_01c9156491') }}</a>
        </header>

        @if(session('status'))<div class="lts-notice">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="lts-errors"><strong>{{ __('lms::ui.auto_13e189f76f') }}</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        <div class="lts-layout">
            <aside class="lts-library">
                <div class="lts-library-head"><strong>{{ __('lms::ui.auto_6605cbc5b7') }}</strong><span>{{ $templates->total() }}</span></div>
                <div class="lts-list">
                    @forelse($templates as $item)
                        <a class="lts-item {{ $isEdit && $item->is($template) ? 'active' : '' }}" href="{{ route('admin.plugins.lms.templates.edit', $item, false) }}">
                            <strong>{{ $item->name }}</strong>
                            <small>{{ __('lms::ui.auto_6a7edf0947') }}</small>
                        </a>
                    @empty
                        <span class="lts-item">{{ __('lms::ui.auto_afb2cdc882') }}</span>
                    @endforelse
                    <x-platform-pagination :paginator="$templates" page-name="templates_page" />
                </div>
            </aside>

            <div>
                <form method="POST" action="{{ $action }}" id="lms-template-form">
                    @csrf
                    @if($isEdit)@method('PUT')@endif

                    <section class="lts-section">
                        <div class="lts-section-head"><h2>{{ __('lms::ui.auto_9150bfe338') }}</h2>@if($template->isSystem())<span class="lts-default-badge">{{ __('lms::ui.auto_2f3fa5086d') }}</span>@endif</div>
                        @if($template->isSystem())<p class="lts-system-note">{{ __('lms::ui.auto_4309e25e8b') }}</p>@endif
                        <div class="lts-meta">
                            <div class="lts-field"><label>{{ __('lms::ui.auto_49ee308734') }}</label><input class="lts-input" name="name" value="{{ old('name',$template->name) }}" required></div>
                            <div class="lts-field"><label>{{ __('lms::ui.auto_0c90858852') }}</label><input class="lts-input" name="slug" value="{{ old('slug',$template->slug) }}" placeholder="{{ __('lms::ui.auto_d4b0437c75') }}"></div>
                            <div class="lts-field"><label>{{ __('lms::ui.auto_86404f70d5') }}</label><select class="lts-select" name="type"><option value="catalog" @selected(old('type',$template->type)==='catalog')>{{ __('lms::ui.auto_8da395444f') }}</option><option value="details" @selected(old('type',$template->type)==='details')>{{ __('lms::ui.auto_a862265abf') }}</option></select></div>
                            <div class="lts-field"><label>{{ __('lms::ui.auto_ec53a8c4f0') }}</label><select class="lts-select" name="status"><option value="active" @selected(old('status',$template->status ?: 'active')==='active')>{{ __('lms::ui.auto_4d3d769b81') }}</option><option value="draft" @selected(old('status',$template->status)==='draft')>{{ __('lms::ui.auto_f03ab16cd5') }}</option></select></div>
                        </div>
                    </section>

                    <section class="lts-section">
                        <div class="lts-section-head"><h2>{{ __('lms::ui.auto_ee818b7e26') }}</h2><span>{{ __('lms::ui.auto_f677d546bd') }}</span></div>
                        <div class="lts-tabs"><button class="lts-tab active" type="button" data-lts-tab="html">HTML</button><button class="lts-tab" type="button" data-lts-tab="css">CSS</button><button class="lts-tab" type="button" data-lts-tab="js">{{ __('lms::ui.auto_686155af75') }}</button><button class="lts-tab" type="button" data-lts-tab="tokens">{{ __('lms::ui.auto_f9491b1143') }}</button></div>
                        <div class="lts-panel active" data-lts-panel="html"><textarea class="lts-code" id="lts-html" name="html_code" spellcheck="false">{{ old('html_code',$template->html_code) }}</textarea><div class="lts-code-status"><span>HTML</span><span data-lines="html"></span></div></div>
                        <div class="lts-panel" data-lts-panel="css"><textarea class="lts-code" id="lts-css" name="css_code" spellcheck="false">{{ old('css_code',$template->css_code) }}</textarea><div class="lts-code-status"><span>CSS</span><span data-lines="css"></span></div></div>
                        <div class="lts-panel" data-lts-panel="js"><textarea class="lts-code" id="lts-js" name="js_code" spellcheck="false">{{ old('js_code',$template->js_code) }}</textarea><div class="lts-code-status"><span>{{ __('lms::ui.auto_686155af75') }}</span><span data-lines="js"></span></div></div>
                        <div class="lts-panel" data-lts-panel="tokens"><div class="lts-tokens">@foreach($tokenGroups as $group=>$entries)<section class="lts-token-group"><h3>{{ $group }}</h3>@foreach($entries as $entry)<button class="lts-token" type="button" data-lts-token="{{ $entry['token'] }}"><code>{{ $entry['token'] }}</code><span>{{ $entry['description'] }}</span></button>@endforeach</section>@endforeach</div></div>
                    </section>

                    <section class="lts-section">
                        <div class="lts-section-head"><h2>{{ __('lms::ui.auto_875b6a864f') }}</h2><button class="lts-button secondary" type="button" data-refresh-preview>{{ __('lms::ui.auto_63a6a88c06') }}</button></div>
                        <div class="lts-preview-stage"><iframe class="lts-preview" id="lts-preview" title="{{ __('lms::ui.auto_caa90060f0') }}" scrolling="no" sandbox="allow-same-origin allow-scripts"></iframe></div>
                        <div class="lts-actions">
                            <div>@if($isEdit && !$template->isSystem())<button class="lts-button danger" type="submit" form="delete-lms-template">{{ __('lms::ui.auto_f2a6c498fb') }}</button>@endif</div>
                            <div>
                                @if($isEdit)<button class="lts-button secondary" type="submit" form="duplicate-lms-template">{{ __('lms::ui.auto_dd70aae7d5') }}</button>@endif
                                @if($isEdit && $template->status==='active' && !$isSelectedDefault)<button class="lts-button secondary" type="submit" form="default-lms-template">{{ $template->type === 'catalog' ? 'Use for Courses Page' : 'Use for Course Details' }}</button>@endif
                                @if($isSelectedCatalog)<span class="lts-default-badge">{{ __('lms::ui.auto_ec5873473d') }}</span>@endif
                                @if($isSelectedDetails)<span class="lts-default-badge">{{ __('lms::ui.auto_0270d92d26') }}</span>@endif
                                <button class="lts-button" type="submit">{{ $isEdit ? 'Save Template' : 'Create Template' }}</button>
                            </div>
                        </div>
                    </section>
                </form>
            </div>
        </div>
    </div>

    @if($isEdit && !$template->isSystem())<form id="delete-lms-template" method="POST" action="{{ route('admin.plugins.lms.templates.destroy',$template,false) }}" hidden>@csrf @method('DELETE')</form>@endif
    @if($isEdit)<form id="duplicate-lms-template" method="POST" action="{{ route('admin.plugins.lms.templates.duplicate',$template,false) }}" hidden>@csrf</form>@endif
    @if($isEdit && in_array($template->type, ['catalog','details'], true))<form id="default-lms-template" method="POST" action="{{ route('admin.plugins.lms.templates.default',$template,false) }}" hidden>@csrf</form>@endif

    <script>
        (() => {
            const html = document.getElementById('lts-html');
            const css = document.getElementById('lts-css');
            const js = document.getElementById('lts-js');
            const preview = document.getElementById('lts-preview');
            const sample = {
                id:'1',slug:'creative-course',title:'Professional Creative Course',url:'#course',subtitle:'Build practical skills through a focused learning path.',short_description:'A concise course summary appears here.',description:'Full course description.',description_html:'Full course description.<br>Designed for practical learning.',image_url:'https://placehold.co/1200x750/f1e5e5/7f1d1d?text=Course',category:'Creative Learning',level:'Beginner',language:'English',instructor_name:@json($lmsBrand),instructor_initial:'A',instructor_title:'Course Instructor',pricing_type:'free',regular_price:'',sale_price:'',price:'0',price_label:'Free',currency:'USD',lesson_count:'3',student_count:'24',duration:'2h 30m',access_duration:'Lifetime',courses_url:'#courses',home_url:'#home',teach_url:'#teach',search_query:'',has_active_filters:'0',category_options:'<option>All Categories</option>',level_options:'<option>All Levels</option>',sort_options:'<option>Newest</option>',empty_state:'',pagination_html:'',site_name:@json($lmsBrand),catalog_title:'Explore Our Courses',catalog_description:'Choose a course and start learning.',results_count:'3',action_button:'<button class="lms-template-details__action" type="button">Enroll in Course</button>',
                outcomes:[{text:'Build practical knowledge'},{text:'Complete a guided project'}],requirements:[{text:'No prior experience required'}],audience:[{text:'Creative learners'}],faqs:[{question:'How long is access?',answer:'You receive lifetime access.'},{question:'Is there a certificate?',answer:'Yes, after completion.'}],lessons:[{number:'1',title:'Introduction',duration:'30m',type:'Video'},{number:'2',title:'Practical Project',duration:'1h',type:'Video'},{number:'3',title:'Final Review',duration:'1h',type:'Text'}]
            };
            const samples = [sample,{...sample,id:'2',slug:'art-business',title:'Art Business Essentials',category:'Business',level:'Intermediate',price:'59.00',price_label:'59.00 USD'},{...sample,id:'3',slug:'digital-storytelling',title:'Digital Storytelling',category:'Media',level:'All levels'}];
            let timer;
            let observer;
            const replace = (markup, values) => markup.replace(/\{\{\{\s*([a-z_]+)\s*\}\}\}/gi,(match,key)=>values[key]??'').replace(/\{\{\s*([a-z_]+)\s*\}\}/gi,(match,key)=>Object.hasOwn(values,key)?values[key]:match);
            const renderCourse = (markup, course) => {
                ['outcomes','requirements','audience','faqs','lessons'].forEach(name => {
                    const pattern = new RegExp(`\\{\\{#${name}\\}\\}([\\s\\S]*?)\\{\\{\\/${name}\\}\\}`,'gi');
                    markup = markup.replace(pattern,(match,row)=>(course[name]||[]).map(item=>replace(row,item)).join(''));
                });
                return replace(markup,course);
            };
            const renderMarkup = markup => replace(markup.replace(/\{\{#courses\}\}([\s\S]*?)\{\{\/courses\}\}/gi,(match,row)=>samples.map(course=>renderCourse(row,course)).join('')),sample);
            const syncHeight = () => {
                const doc=preview.contentDocument;if(!doc?.body)return;
                const resize=()=>{const height=Math.max(doc.body.scrollHeight,doc.documentElement.scrollHeight);preview.style.height=`${Math.min(2000,Math.max(360,height+2))}px`;};
                resize();observer?.disconnect();observer=new ResizeObserver(resize);observer.observe(doc.body);
            };
            const render = () => {
                const runtime=js.value ? `<scr${'ipt'}>${js.value.split('</scr'+'ipt').join('<\\/scr'+'ipt')}</scr${'ipt'}>` : '';
                preview.srcdoc=`<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>html,body{margin:0;overflow:hidden;background:#fff}${css.value}</style></head><body><div data-lms-template="preview">${renderMarkup(html.value)}</div>${runtime}</body></html>`;
                ['html','css','js'].forEach(name=>document.querySelector(`[data-lines="${name}"]`).textContent=`${document.getElementById(`lts-${name}`).value.split(/\n/).length} lines`);
            };
            preview.addEventListener('load',syncHeight);
            [html,css,js].forEach(editor=>{editor.addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(render,120)});editor.addEventListener('keydown',event=>{if(event.key!=='Tab')return;event.preventDefault();editor.setRangeText('    ',editor.selectionStart,editor.selectionEnd,'end')})});
            document.querySelectorAll('[data-lts-tab]').forEach(button=>button.addEventListener('click',()=>{document.querySelectorAll('[data-lts-tab]').forEach(item=>item.classList.toggle('active',item===button));document.querySelectorAll('[data-lts-panel]').forEach(panel=>panel.classList.toggle('active',panel.dataset.ltsPanel===button.dataset.ltsTab))}));
            document.querySelectorAll('[data-lts-token]').forEach(button=>button.addEventListener('click',()=>{const value=button.dataset.ltsToken;html.setRangeText(value,html.selectionStart,html.selectionEnd,'end');html.dispatchEvent(new Event('input'));html.focus()}));
            document.querySelector('[data-refresh-preview]')?.addEventListener('click',render);
            render();
        })();
    </script>
</x-app-layout>
