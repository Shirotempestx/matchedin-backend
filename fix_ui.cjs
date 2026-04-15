const fs = require('fs');

const path = 'C:/Users/pc/OneDrive/Desktop/MatchedIn-v2/MatchendIN/FrontEnd/src/pages/explore/FollowedEnterprises.tsx';
let content = fs.readFileSync(path, 'utf8');

const regex = /{enterprises\.map\(\(enterprise\) => \([\s\S]*?\}\)/;

const newBlock = \{enterprises.map((enterprise) => (
                            <motion.div 
                                key={enterprise.id}
                                whileHover={{ y: -4 }}
                                className="p-8 bg-white/[0.02] border border-white/5 rounded-[40px] group hover:border-[#1464da]/20 transition-all relative overflow-hidden flex flex-col h-full hover:shadow-[0_0_30px_rgba(20,100,218,0.05)] cursor-pointer"
                                onClick={() => {
                                    const slug = getEnterpriseSlug(enterprise);
                                    navigate(\\\/\/enterprises/\\\\);
                                }}
                            >
                                {/* Glow effect */}
                                <div className="absolute top-0 right-0 w-40 h-40 bg-[#1464da]/5 rounded-full blur-[60px] -mr-20 -mt-20 group-hover:bg-[#1464da]/10 transition-colors pointer-events-none" />

                                <div className="flex items-center gap-5 mb-8 relative z-10">
                                    <div className="w-16 h-16 rounded-[24px] bg-white/[0.05] border border-white/10 flex items-center justify-center overflow-hidden flex-shrink-0 shadow-[inset_0_2px_10px_rgba(255,255,255,0.05)] font-black text-white text-xl relative">
                                        {enterprise.logo_url ? (
                                            <img src={enterprise.logo_url} alt={enterprise.company_name || enterprise.name || ''} className="h-full w-full object-cover relative z-10" />
                                        ) : (
                                            (enterprise.company_name || enterprise.name || '?').charAt(0).toUpperCase()
                                        )}
                                    </div>
                                    <div className="flex-1 mt-1">
                                        <h3 className="text-xl font-black tracking-tight text-white group-hover:text-[#1464da] transition-colors uppercase leading-tight line-clamp-2">
                                            {enterprise.company_name || enterprise.name || 'Entreprise sans nom'}
                                        </h3>
                                        <div className="flex items-center gap-2 text-[11px] font-bold text-slate-500 uppercase tracking-widest mt-2">
                                            <Location01Icon size={14} className="text-[#1464da]" />
                                            <span className="truncate">{enterprise.location || enterprise.country || 'Localisation non définie'}</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <p className="text-[13px] text-slate-400 font-medium line-clamp-3 leading-relaxed relative z-10 flex-1">
                                    {enterprise.description || 'Aucune description disponible pour cette entreprise.'}
                                </p>

                                <div className="flex items-center gap-3 relative z-10 w-full mt-8">
                                    <span className="px-4 py-3.5 bg-[#1464da]/10 border border-[#1464da]/20 rounded-[20px] text-[10px] font-black uppercase tracking-widest text-[#1464da] shadow-sm flex items-center justify-center">
                                        {enterprise.industry || 'Secteur non défini'}
                                    </span>
                                    <button
                                        className="flex-1 py-3.5 bg-white/[0.03] hover:bg-white/[0.05] border border-white/5 hover:border-[#1464da]/30 rounded-[20px] text-[10px] font-black uppercase tracking-widest flex items-center justify-center gap-2 transition-all group/btn text-white"
                                    >
                                        {'VOIR L\\'ENTREPRISE'}
                                        <ArrowRight01Icon size={14} className="group-hover/btn:translate-x-1 transition-transform" />
                                    </button>
                                </div>
                            </motion.div>
                        ))}\;

content = content.replace(regex, newBlock);
fs.writeFileSync(path, content, 'utf8');
console.log('Update finished!');
