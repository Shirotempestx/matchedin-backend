
const fs = require("fs");
const newForm = `<form onSubmit={handleSearch} className="flex flex-col gap-4">
                          <div className="flex-1 flex flex-col gap-4">
                              <div className="flex flex-col md:flex-row gap-4"> 
                                  <div className="flex-[2] relative group">     
                                      <Search01Icon size={18} className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-emerald-500 transition-colors" />
                                      <input type="text" placeholder={routeLocale === "fr" ? "Rechercher un nom ou un profil..." : "Search name or profile..."} value={search}
                                          onChange={e => setSearch(e.target.value)}
                                          className="w-full pl-11 pr-4 py-3 bg-white/[0.03] hover:bg-white/[0.05] border border-white/10 rounded-2xl text-[13px] font-medium text-white focus:outline-none focus:border-emerald-500 focus:bg-white/[0.05] transition-all placeholder:text-slate-500 placeholder:font-normal"      
                                      />
                                  </div>
                                  <div className="flex-[1] relative group">     
                                      <Location01Icon size={18} className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-emerald-500 transition-colors" />
                                      <input type="text" placeholder={routeLocale === "fr" ? "Ville / Pays" : "City / Country"} value={locSearch}
                                          onChange={e => setLocSearch(e.target.value)}
                                          className="w-full pl-11 pr-4 py-3 bg-white/[0.03] hover:bg-white/[0.05] border border-white/10 rounded-2xl text-[13px] font-medium text-white focus:outline-none focus:border-emerald-500 focus:bg-white/[0.05] transition-all placeholder:text-slate-500 placeholder:font-normal"      
                                      />
                                  </div>
                              </div>
                              <div className="flex flex-col md:flex-row gap-4"> 
                                  <div className="flex-[2] relative group">     
                                      <Briefcase02Icon size={18} className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-emerald-500 transition-colors" />
                                      <select
                                          value={profileTypeSearch}
                                          onChange={e => setProfileTypeSearch(e.target.value)}
                                          className="w-full pl-11 pr-10 py-3 bg-[#0f172a] hover:bg-white/[0.05] border border-white/10 rounded-2xl text-[13px] font-medium text-white focus:outline-none focus:border-emerald-500 focus:bg-[#0f172a] transition-all appearance-none cursor-pointer [&>option]:text-slate-900 [&>option]:bg-white"
                                      >
                                          <option value="">{routeLocale === "fr" ? "Type de profil..." : "Profile type..."}</option>
                                          <option value="IT">IT / Tech</option> 
                                          <option value="NON_IT">Non-IT / Business</option>
                                      </select>
                                      <div className="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-slate-500">
                                          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                                      </div>
                                  </div>
                                  <div className="flex-[2] relative group">     
                                      <Book02Icon size={18} className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-emerald-500 transition-colors" />
                                      <select
                                          value={educationLevelSearch}
                                          onChange={e => setEducationLevelSearch(e.target.value)}
                                          className="w-full pl-11 pr-10 py-3 bg-[#0f172a] hover:bg-white/[0.05] border border-white/10 rounded-2xl text-[13px] font-medium text-white focus:outline-none focus:border-emerald-500 focus:bg-[#0f172a] transition-all appearance-none cursor-pointer [&>option]:text-slate-900 [&>option]:bg-white"
                                      >
                                          <option value="">{routeLocale === "fr" ? "Niveau d`" + "études..." : "Education level..."}</option>
                                          <option value="Bac+2">Bac+2</option>  
                                          <option value="Bac+3">Bac+3 / Licence</option>
                                          <option value="Bac+5">Bac+5 / Master</option>
                                          <option value="Doctorat">Doctorat / Ph.D</option>
                                          <option value="Bootcamp">Bootcamp / Certification</option>
                                      </select>
                                      <div className="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-slate-500">
                                          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                                      </div>
                                  </div>
                                  <div className="flex-[1] relative group">     
                                      <MoneyBag02Icon size={18} className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-emerald-500 transition-colors" />
                                      <input type="number" placeholder={routeLocale === "fr" ? "Salaire max" : "Max salary"} value={salaryMax}
                                          onChange={e => setSalaryMax(e.target.value ? Number(e.target.value) : "")}
                                          className="w-full pl-11 pr-4 py-3 bg-white/[0.03] hover:bg-white/[0.05] border border-white/10 rounded-2xl text-[13px] font-medium text-white focus:outline-none focus:border-emerald-500 focus:bg-white/[0.05] transition-all placeholder:text-slate-500 placeholder:font-normal"      
                                      />
                                  </div>
                              </div>
                          </div>
                          <div className="w-full pt-1">
                              <button type="submit" className="w-full py-3.5 bg-emerald-600 hover:bg-emerald-500 rounded-2xl text-[13px] font-semibold tracking-wide transition-all shadow-md shadow-emerald-500/20 flex items-center justify-center gap-2">
                                  <Search01Icon size={18} />
                                  <span>{routeLocale === "fr" ? "Rechercher" : "Search"}</span>
                              </button>
                          </div>
                      </form>`;

let code = fs.readFileSync("C:/Users/pc/OneDrive/Desktop/MatchedIn-v2/MatchendIN/FrontEnd/src/pages/explore/ExploreStudents.tsx", "utf8");
const oldFormRegex = /<form onSubmit=\{handleSearch\} className="flex flex-col gap-4">[\s\S]*?<\/form>/;

if(oldFormRegex.test(code)) {
    code = code.replace(oldFormRegex, newForm);
    fs.writeFileSync("C:/Users/pc/OneDrive/Desktop/MatchedIn-v2/MatchendIN/FrontEnd/src/pages/explore/ExploreStudents.tsx", code);
    console.log("Success");
} else {
    console.error("Form not found in code.");
}
